<?php

namespace Database\Seeders;

use App\Model\Internship\Internship;
use App\Model\Seminar\Seminar;
use Illuminate\Database\Seeder;

class SeminarInternshipSeeder extends Seeder
{
    public function run(): void
    {
        $seminars = [
            [
                'slug' => 'it-placement-seminar',
                'title' => 'IT Placement Seminar',
                'tagline' => 'Land your first tech job',
                'blurb' => 'A hands-on seminar covering resume building, DSA prep, system design basics and interview strategy for IT placements.',
                'date' => 'Every Saturday, 11:00 AM',
                'mode' => 'Online',
                'duration' => '90 minutes',
                'audience' => 'Final-year students & freshers',
                'emoji' => '💻',
                'highlights' => ['Resume & portfolio review', 'Interview preparation roadmap', 'Real-world project insights', 'Q&A with industry mentors'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'upsc-strategy-seminar',
                'title' => 'UPSC Strategy Seminar',
                'tagline' => 'Plan your civil services prep',
                'blurb' => 'Learn a realistic preparation strategy, answer-writing techniques and optional-subject selection from mentors who cleared the exam.',
                'date' => 'First Sunday of every month, 5:00 PM',
                'mode' => 'Online',
                'duration' => '2 hours',
                'audience' => 'UPSC aspirants',
                'emoji' => '🏛️',
                'highlights' => ['Prelims + Mains strategy', 'Answer writing framework', 'Optional subject guidance', 'Time management tips'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'neet-crash-seminar',
                'title' => 'NEET Score Booster Seminar',
                'tagline' => 'Sharpen your NEET prep',
                'blurb' => 'A focused seminar on high-yield topics, revision planning and test-taking strategy for NEET-UG.',
                'date' => 'Alternate Saturdays, 4:00 PM',
                'mode' => 'Online',
                'duration' => '90 minutes',
                'audience' => 'NEET aspirants',
                'emoji' => '🩺',
                'highlights' => ['High-yield topic mapping', 'Revision & mock plan', 'NCERT mastery tips', 'Doubt-clearing session'],
                'sort_order' => 3,
            ],
        ];

        foreach ($seminars as $data) {
            Seminar::updateOrCreate(['slug' => $data['slug']], array_merge($data, [
                'status' => 'active',
                'is_published' => true,
            ]));
        }

        $internships = [
            [
                'slug' => 'frontend-developer',
                'role' => 'Frontend Developer Intern',
                'team' => 'Engineering',
                'location' => 'Bangalore',
                'type' => 'Hybrid',
                'duration' => '3-6 months',
                'stipend' => '₹15,000-25,000/mo',
                'blurb' => 'Build delightful, high-performance interfaces for the MentorKhoj web platform using React and Next.js.',
                'skills' => ['React', 'Next.js', 'TypeScript', 'Tailwind CSS'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'backend-developer',
                'role' => 'Backend Developer Intern',
                'team' => 'Engineering',
                'location' => 'Remote',
                'type' => 'Remote',
                'duration' => '3-6 months',
                'stipend' => '₹15,000-25,000/mo',
                'blurb' => 'Design APIs and services that power mentorship bookings, payments and live sessions.',
                'skills' => ['PHP/Laravel', 'REST APIs', 'MySQL', 'Git'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'product-design',
                'role' => 'Product Design Intern',
                'team' => 'Design',
                'location' => 'Remote',
                'type' => 'Remote',
                'duration' => '3 months',
                'stipend' => '₹12,000-20,000/mo',
                'blurb' => 'Craft intuitive flows and a premium visual language across web and mobile.',
                'skills' => ['Figma', 'UX Research', 'Prototyping', 'Design Systems'],
                'sort_order' => 3,
            ],
            [
                'slug' => 'marketing-growth',
                'role' => 'Marketing & Growth Intern',
                'team' => 'Growth',
                'location' => 'Bangalore',
                'type' => 'On-site',
                'duration' => '3 months',
                'stipend' => '₹10,000-18,000/mo',
                'blurb' => 'Run campaigns, SEO experiments and content that bring learners to their mentors.',
                'skills' => ['SEO', 'Content', 'Social Media', 'Analytics'],
                'sort_order' => 4,
            ],
            [
                'slug' => 'community-operations',
                'role' => 'Community & Operations Intern',
                'team' => 'Operations',
                'location' => 'Remote',
                'type' => 'Remote',
                'duration' => '3 months',
                'stipend' => '₹10,000-15,000/mo',
                'blurb' => 'Onboard mentors, coordinate sessions and keep our community thriving.',
                'skills' => ['Communication', 'Coordination', 'CRM', 'Support'],
                'sort_order' => 5,
            ],
        ];

        foreach ($internships as $data) {
            Internship::updateOrCreate(['slug' => $data['slug']], array_merge($data, [
                'status' => 'active',
                'is_published' => true,
            ]));
        }
    }
}
