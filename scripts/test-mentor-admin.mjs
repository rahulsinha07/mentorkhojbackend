#!/usr/bin/env node
/**
 * Static + API validation for admin mentor list module.
 * Run: node scripts/test-mentor-admin.mjs
 */

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, "..");
const API_BASE = process.env.API_BASE || "https://aihealtheval.com";
const ADMIN_BASE = process.env.ADMIN_BASE || API_BASE;

const results = [];
let passed = 0;
let failed = 0;

function ok(name, detail = "") {
  passed++;
  results.push({ status: "PASS", name, detail });
}

function fail(name, detail = "") {
  failed++;
  results.push({ status: "FAIL", name, detail });
}

function read(rel) {
  return fs.readFileSync(path.join(ROOT, rel), "utf8");
}

function exists(rel) {
  return fs.existsSync(path.join(ROOT, rel));
}

// --- PHP status/publish logic mirror (matches MentorController) ---
function phpTruthy(value) {
  if (value === false || value === null) return false;
  if (value === 0 || value === 0.0) return false;
  if (value === "0" || value === "") return false;
  return Boolean(value);
}

function mentorStatusFromRouteParam(statusParam) {
  return phpTruthy(statusParam) ? "active" : "draft";
}

function mentorPublishFromRouteParam(isPublishedParam) {
  return Boolean(isPublishedParam) && isPublishedParam !== "0" && isPublishedParam !== 0;
}

function testToggleLogic() {
  const cases = [
    { fn: mentorStatusFromRouteParam, input: 1, expected: "active" },
    { fn: mentorStatusFromRouteParam, input: 0, expected: "draft" },
    { fn: mentorStatusFromRouteParam, input: "1", expected: "active" },
    { fn: mentorStatusFromRouteParam, input: "0", expected: "draft" },
    { fn: mentorPublishFromRouteParam, input: 1, expected: true },
    { fn: mentorPublishFromRouteParam, input: 0, expected: false },
    { fn: mentorPublishFromRouteParam, input: "1", expected: true },
    { fn: mentorPublishFromRouteParam, input: "0", expected: false },
  ];

  for (const { fn, input, expected } of cases) {
    const got = fn(input);
    if (got === expected) {
      ok(`toggle logic ${fn.name}(${JSON.stringify(input)})`, `=> ${JSON.stringify(got)}`);
    } else {
      fail(`toggle logic ${fn.name}(${JSON.stringify(input)})`, `expected ${JSON.stringify(expected)}, got ${JSON.stringify(got)}`);
    }
  }
}

function testFilesAndRoutes() {
  const required = [
    "app/Http/Controllers/Admin/MentorController.php",
    "resources/views/admin-views/mentor/list.blade.php",
    "routes/admin.php",
    "app/Model/Mentor/Mentor.php",
  ];

  for (const file of required) {
    if (exists(file)) ok(`file exists: ${file}`);
    else fail(`file exists: ${file}`, "missing");
  }

  const adminRoutes = read("routes/admin.php");
  const routeChecks = [
    ["MentorController import", /use App\\Http\\Controllers\\Admin\\MentorController;/],
    ["mentor list route", /Route::get\('list', \[MentorController::class, 'list'\]\)/],
    ["mentor status route", /Route::get\('status\/\{id\}\/\{status\}', \[MentorController::class, 'status'\]\)/],
    ["mentor publish route", /Route::get\('publish\/\{id\}\/\{is_published\}', \[MentorController::class, 'publish'\]\)/],
    ["mentor delete route", /Route::delete\('delete\/\{id\}', \[MentorController::class, 'delete'\]\)/],
  ];

  for (const [name, pattern] of routeChecks) {
    if (pattern.test(adminRoutes)) ok(`route: ${name}`);
    else fail(`route: ${name}`, "not found in routes/admin.php");
  }

  const controller = read("app/Http/Controllers/Admin/MentorController.php");
  const controllerChecks = [
    ["list method", /public function list\(/],
    ["status method", /public function status\(/],
    ["publish method", /public function publish\(/],
    ["delete method", /public function delete\(/],
    ["withCount services/bookings", /withCount\(\['services', 'bookings'\]\)/],
    ["status maps to active/draft", /\$mentor->status = \$request->status \? 'active' : 'draft'/],
    ["publish casts bool", /\$mentor->is_published = \(bool\) \$request->is_published/],
  ];

  for (const [name, pattern] of controllerChecks) {
    if (pattern.test(controller)) ok(`controller: ${name}`);
    else fail(`controller: ${name}`, "pattern not found");
  }

  const blade = read("resources/views/admin-views/mentor/list.blade.php");
  const bladeChecks = [
    ["publish toggle route", /route\('admin\.mentor\.publish'/],
    ["status toggle route", /route\('admin\.mentor\.status'/],
    ["delete form route", /route\('admin\.mentor\.delete'/],
    ["profile URL helper", /MentorLogic::profileUrl\(\$mentor\)/],
    ["status_change_alert script", /function status_change_alert/],
  ];

  for (const [name, pattern] of bladeChecks) {
    if (pattern.test(blade)) ok(`blade: ${name}`);
    else fail(`blade: ${name}`, "pattern not found");
  }

  const sidebar = read("resources/views/layouts/admin/partials/_sidebar.blade.php");
  if (/admin\.mentor\.list/.test(sidebar)) ok("sidebar: mentor list link");
  else fail("sidebar: mentor list link", "not found");
}

async function testApiAndAdmin() {
  const mentorsRes = await fetch(`${API_BASE}/api/v1/mentors?limit=5`, {
    headers: { "X-localization": "en" },
  });

  if (!mentorsRes.ok) {
    fail("mentors API", `HTTP ${mentorsRes.status}`);
    return;
  }

  const data = await mentorsRes.json();
  const mentors = data.mentors ?? [];

  if (Array.isArray(mentors) && mentors.length > 0) {
    ok("mentors API returns data", `${mentors.length} mentors (total ${data.total_size ?? "?"})`);
  } else {
    fail("mentors API returns data", "empty mentors array");
  }

  const requiredFields = ["id", "username", "display_name", "is_published"];
  for (const mentor of mentors.slice(0, 3)) {
    const missing = requiredFields.filter((f) => mentor[f] === undefined);
    if (missing.length === 0) {
      ok(`mentor #${mentor.id} has list fields`, mentor.username);
    } else {
      fail(`mentor #${mentor.id} has list fields`, `missing: ${missing.join(", ")}`);
    }
  }

  const adminRes = await fetch(`${ADMIN_BASE}/admin/mentor/list`, { redirect: "manual" });
  if (adminRes.status === 404) {
    fail(
      "admin mentor list deployed",
      "HTTP 404 — deploy MentorController + routes to production",
    );
  } else if ([301, 302, 303, 307, 308].includes(adminRes.status)) {
    ok("admin mentor list deployed", `HTTP ${adminRes.status} (auth redirect expected)`);
  } else if (adminRes.status === 200) {
    ok("admin mentor list deployed", "HTTP 200");
  } else if (adminRes.status === 403) {
    ok("admin mentor list deployed", "HTTP 403 (module/auth gate)");
  } else {
    fail("admin mentor list deployed", `unexpected HTTP ${adminRes.status}`);
  }
}

async function main() {
  console.log("Mentor admin module validation\n");
  testToggleLogic();
  testFilesAndRoutes();
  await testApiAndAdmin();

  console.log("\nResults:");
  for (const r of results) {
    const mark = r.status === "PASS" ? "✓" : "✗";
    console.log(`  ${mark} ${r.name}${r.detail ? ` — ${r.detail}` : ""}`);
  }

  console.log(`\n${passed} passed, ${failed} failed`);
  process.exit(failed > 0 ? 1 : 0);
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
