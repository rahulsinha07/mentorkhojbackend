<?php

namespace Database\Seeders;

use App\Model\Mentor\MentorShareTemplate;
use Illuminate\Database\Seeder;

class MentorShareTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'profile-card',
                'title' => "Let's connect",
                'subtitle' => 'Personal profile card',
                'poster_image' => 'profile-card',
                'default_caption' => "Hey folks! I've been getting a lot of DMs for guidance, so decided to take action on it. I'm excited to help folks out and give back to the community via MentorKhoj. Don't hesitate to reach out if you have any questions or just want to say hi!",
                'hashtags' => json_encode(['MentorKhoj', 'Mentorship', 'CareerGrowth']),
                'sort_order' => 0,
            ],
            [
                'slug' => 'new-announcement',
                'title' => 'New Announcement Poster',
                'subtitle' => 'Converts Better',
                'poster_image' => 'new-announcement.png',
                'default_caption' => "I'm now offering 1-on-1 mentorship on MentorKhoj! Book a session with {name} and get personalised guidance.\n\n{headline}",
                'hashtags' => json_encode(['MentorKhoj', 'Mentorship', 'CareerGrowth']),
                'sort_order' => 1,
            ],
            [
                'slug' => 'im-available',
                'title' => "I'm available",
                'subtitle' => 'Campaign',
                'poster_image' => 'im-available.png',
                'default_caption' => "I'm available for mentorship sessions! Whether you need career guidance, interview prep, or resume review — let's connect.\n\nBook me on MentorKhoj: {url}",
                'hashtags' => json_encode(['MentorKhoj', 'Mentorship', 'CareerAdvice']),
                'sort_order' => 2,
            ],
            [
                'slug' => 'top-mentor-badge',
                'title' => 'Top Mentor Badge',
                'subtitle' => 'Social proof',
                'poster_image' => 'top-mentor-badge.png',
                'default_caption' => "Proud to be a mentor on MentorKhoj! Help me spread the word — book a session and let's achieve your goals together.\n\n{headline}",
                'hashtags' => json_encode(['MentorKhoj', 'TopMentor', 'Mentorship']),
                'sort_order' => 3,
            ],
        ];

        foreach ($templates as $template) {
            MentorShareTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
