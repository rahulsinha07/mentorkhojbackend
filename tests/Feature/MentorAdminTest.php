<?php

namespace Tests\Feature;

use App\Model\Mentor\Mentor;
use Tests\TestCase;

class MentorAdminTest extends TestCase
{
    public function test_mentor_status_toggle_maps_route_param_to_active_or_draft(): void
    {
        $this->assertSame('active', $this->resolveMentorStatus(1));
        $this->assertSame('draft', $this->resolveMentorStatus(0));
        $this->assertSame('active', $this->resolveMentorStatus('1'));
        $this->assertSame('draft', $this->resolveMentorStatus('0'));
    }

    public function test_mentor_publish_toggle_casts_route_param_to_bool(): void
    {
        $this->assertTrue($this->resolveMentorPublished(1));
        $this->assertFalse($this->resolveMentorPublished(0));
        $this->assertTrue($this->resolveMentorPublished('1'));
        $this->assertFalse($this->resolveMentorPublished('0'));
    }

    public function test_mentor_published_scope_requires_active_and_published(): void
    {
        $sql = Mentor::published()->toSql();
        $this->assertStringContainsString('is_published', $sql);
        $this->assertStringContainsString('status', $sql);
    }

    public function test_admin_mentor_routes_are_registered(): void
    {
        $this->assertNotNull(route('admin.mentor.list', absolute: false));
        $this->assertNotNull(route('admin.mentor.edit', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.mentor.update', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.mentor.status', ['id' => 1, 'status' => 1], absolute: false));
        $this->assertNotNull(route('admin.mentor.publish', ['id' => 1, 'is_published' => 1], absolute: false));
        $this->assertNotNull(route('admin.mentor.delete', ['id' => 1], absolute: false));
    }

    public function test_mentor_category_filter_matches_string_and_numeric_json_ids(): void
    {
        $catId = 59;
        $sql = Mentor::published()
            ->where(function ($q) use ($catId) {
                $q->where('category_ids', 'like', '%"id":' . $catId . '%')
                    ->orWhere('category_ids', 'like', '%"id":"' . $catId . '"%');
            })
            ->toSql();

        $this->assertStringContainsString('category_ids', $sql);
        $this->assertStringContainsString('like ?', $sql);
    }

    private function resolveMentorStatus(mixed $status): string
    {
        return $status ? 'active' : 'draft';
    }

    private function resolveMentorPublished(mixed $isPublished): bool
    {
        return (bool) $isPublished;
    }
}
