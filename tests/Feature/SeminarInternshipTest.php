<?php

namespace Tests\Feature;

use App\CentralLogics\FormMailLogic;
use App\CentralLogics\InternshipLogic;
use App\CentralLogics\SeminarLogic;
use App\Model\Internship\Internship;
use App\Model\Seminar\Seminar;
use Tests\TestCase;

class SeminarInternshipTest extends TestCase
{
    public function test_seminar_api_routes_are_registered(): void
    {
        $this->assertNotNull(route('admin.seminar.list', absolute: false));
        $this->assertNotNull(route('admin.seminar.add', absolute: false));
        $this->assertNotNull(route('admin.seminar.store', absolute: false));
        $this->assertNotNull(route('admin.seminar.edit', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.seminar.update', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.seminar.status', ['id' => 1, 'status' => 'active'], absolute: false));
        $this->assertNotNull(route('admin.seminar.publish', ['id' => 1, 'is_published' => 1], absolute: false));
        $this->assertNotNull(route('admin.seminar.delete', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.seminar.registrations', absolute: false));
    }

    public function test_internship_api_routes_are_registered(): void
    {
        $this->assertNotNull(route('admin.internship.list', absolute: false));
        $this->assertNotNull(route('admin.internship.add', absolute: false));
        $this->assertNotNull(route('admin.internship.store', absolute: false));
        $this->assertNotNull(route('admin.internship.edit', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.internship.update', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.internship.status', ['id' => 1, 'status' => 'active'], absolute: false));
        $this->assertNotNull(route('admin.internship.publish', ['id' => 1, 'is_published' => 1], absolute: false));
        $this->assertNotNull(route('admin.internship.delete', ['id' => 1], absolute: false));
        $this->assertNotNull(route('admin.internship.applications', absolute: false));
    }

    public function test_seminar_logic_formats_public_payload(): void
    {
        $seminar = new Seminar([
            'id' => 1,
            'slug' => 'test-seminar',
            'title' => 'Test Seminar',
            'tagline' => 'Learn fast',
            'blurb' => 'A test seminar',
            'date' => 'Saturday 11 AM',
            'mode' => 'Online',
            'duration' => '90 minutes',
            'audience' => 'Students',
            'emoji' => '💻',
            'highlights' => ['Topic A', 'Topic B'],
            'status' => 'active',
            'is_published' => true,
        ]);

        $formatted = SeminarLogic::formatPublic($seminar);

        $this->assertSame('test-seminar', $formatted['slug']);
        $this->assertSame('Test Seminar', $formatted['title']);
        $this->assertTrue($formatted['accepting_registrations']);
        $this->assertSame(['Topic A', 'Topic B'], $formatted['highlights']);
    }

    public function test_internship_logic_formats_public_payload(): void
    {
        $internship = new Internship([
            'id' => 1,
            'slug' => 'frontend-developer',
            'role' => 'Frontend Developer Intern',
            'team' => 'Engineering',
            'location' => 'Remote',
            'type' => 'Remote',
            'duration' => '3 months',
            'stipend' => '₹15,000/mo',
            'blurb' => 'Build UI',
            'skills' => ['React', 'TypeScript'],
            'status' => 'active',
            'is_published' => true,
        ]);

        $formatted = InternshipLogic::formatPublic($internship);

        $this->assertSame('frontend-developer', $formatted['slug']);
        $this->assertSame('Frontend Developer Intern', $formatted['role']);
        $this->assertTrue($formatted['accepting_applications']);
        $this->assertSame(['React', 'TypeScript'], $formatted['skills']);
    }

    public function test_seminar_logic_parses_highlights_from_text(): void
    {
        $highlights = SeminarLogic::parseHighlights("Resume tips\nInterview prep\nQ&A session");
        $this->assertSame(['Resume tips', 'Interview prep', 'Q&A session'], $highlights);
    }

    public function test_internship_logic_parses_skills_from_csv(): void
    {
        $skills = InternshipLogic::parseSkills('React, Next.js, TypeScript');
        $this->assertSame(['React', 'Next.js', 'TypeScript'], $skills);
    }

    public function test_form_mail_logic_admin_email_has_sensible_default(): void
    {
        $email = FormMailLogic::adminEmail();
        $this->assertNotEmpty($email);
        $this->assertStringContainsString('@', $email);
    }

    public function test_form_mail_logic_brand_context_includes_logo_and_site(): void
    {
        $brand = FormMailLogic::brandContext();

        $this->assertSame('MentorKhoj', $brand['site_name']);
        $this->assertStringContainsString('mentorkhoj.com', $brand['site_url']);
        $this->assertNotEmpty(FormMailLogic::brandLogoUrl());
        $this->assertStringStartsWith('http', FormMailLogic::brandLogoUrl());
        $this->assertStringContainsString('/seminars', $brand['seminars_url']);
        $this->assertStringContainsString('/internships', $brand['internships_url']);
    }

    public function test_seminar_registration_id_has_sem_prefix(): void
    {
        $id = SeminarLogic::generateRegistrationId();
        $this->assertStringStartsWith('SEM-', $id);
    }

    public function test_internship_application_id_has_int_prefix(): void
    {
        $id = InternshipLogic::generateApplicationId();
        $this->assertStringStartsWith('INT-', $id);
    }
}
