<?php

return [

    'name' => env('INSTITUTE_NAME', env('APP_NAME', 'School CRM')),

    'tagline' => env('INSTITUTE_TAGLINE', 'School & Coaching Management'),

    'number_prefix' => env('INSTITUTE_NUMBER_PREFIX', 'CRM'),

    /*
    | Deployment profile — one per installation: school, coaching, or college.
    | Super Admin can change this under Setup → Institute Setup (saved in DB).
    */
    'type' => env('INSTITUTE_TYPE', 'school'),

    'hero' => [
        'title' => env('INSTITUTE_HERO_TITLE', 'Quality Education for Every Student'),
        'subtitle' => env('INSTITUTE_HERO_SUBTITLE', 'Manage admissions, fees, batches, and attendance — built for schools and coaching institutes. Start your learning journey with us.'),
    ],

    'about' => env('INSTITUTE_ABOUT', 'We are a school and coaching institute focused on academic excellence and student success. From classroom programmes to competitive exam coaching, we help students achieve their goals with structured learning and dedicated faculty.'),

    'phone' => env('INSTITUTE_PHONE', '+91 98765 43210'),

    'whatsapp' => env('INSTITUTE_WHATSAPP', '919876543210'),

    'email' => env('INSTITUTE_EMAIL', 'info@example.com'),

    'address' => env('INSTITUTE_ADDRESS', 'Your Institute Address, India'),

    'city' => env('INSTITUTE_CITY', ''),

    'hours' => env('INSTITUTE_HOURS', 'Mon – Sat: 9:00 AM – 6:00 PM'),

    'established' => env('INSTITUTE_ESTABLISHED', '2010'),

    'receipt_footer' => env(
        'INSTITUTE_RECEIPT_FOOTER',
        'This is a computer-generated receipt. Fees once paid are non-refundable except as per institute policy. Please retain this receipt for your records.',
    ),

    'storage' => [
        'livewire_temp_max_age_hours' => (int) env('INSTITUTE_LIVEWIRE_TEMP_MAX_AGE_HOURS', 24),
    ],

    'social' => [
        'facebook' => env('INSTITUTE_FACEBOOK', ''),
        'instagram' => env('INSTITUTE_INSTAGRAM', ''),
        'youtube' => env('INSTITUTE_YOUTUBE', ''),
    ],

    'images' => [
        'hero' => [
            'main' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=1920&q=80',
            'accent_one' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=800&q=80',
            'accent_two' => 'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?auto=format&fit=crop&w=800&q=80',
            'about' => 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?auto=format&fit=crop&w=900&q=80',
        ],
        'gallery' => [
            [
                'src' => 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?auto=format&fit=crop&w=900&q=80',
                'alt' => 'Students on campus',
                'caption' => 'Campus & Classrooms',
                'span' => 'sm:col-span-2 sm:min-h-[280px] lg:col-span-2 lg:row-span-2',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Interactive classroom session',
                'caption' => 'Interactive Learning',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Library and study area',
                'caption' => 'Library & Study Hall',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Group study and collaboration',
                'caption' => 'Group Study Sessions',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?auto=format&fit=crop&w=900&q=80',
                'alt' => 'Coaching and exam preparation',
                'caption' => 'Exam Coaching',
                'span' => 'sm:col-span-2 sm:min-h-[240px] lg:col-span-2',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Science laboratory',
                'caption' => 'Science Lab',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1541339907198-e08756dedf3d?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Graduation and achievements',
                'caption' => 'Student Achievements',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Faculty mentoring students',
                'caption' => 'Faculty Mentorship',
                'span' => '',
            ],
        ],
    ],

];
