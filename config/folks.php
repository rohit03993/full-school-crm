<?php

return [

    'name' => env('FOLKS_INSTITUTE_NAME', 'Folks India'),

    'tagline' => env('FOLKS_TAGLINE', 'Institute of Hotel Management & Hospitality'),

    'hero' => [
        'title' => env('FOLKS_HERO_TITLE', 'Build Your Career in Hospitality'),
        'subtitle' => env('FOLKS_HERO_SUBTITLE', 'Industry-focused training in hotel management — from front office to food production. Start your journey with Folks India.'),
    ],

    'about' => env('FOLKS_ABOUT', 'Folks India is a premier hospitality training institute offering BSc and Diploma programmes in Hotel Management. Our practical, industry-aligned curriculum prepares students for careers in hotels, resorts, cruise lines, and food service worldwide.'),

    'phone' => env('FOLKS_PHONE', '+91 70170 57275'),

    'whatsapp' => env('FOLKS_WHATSAPP', '917017057275'),

    'email' => env('FOLKS_EMAIL', 'info@folksindia.com'),

    'address' => env('FOLKS_ADDRESS', 'Folks India Campus, India'),

    'city' => env('FOLKS_CITY', ''),

    'hours' => env('FOLKS_HOURS', 'Mon – Sat: 9:00 AM – 6:00 PM'),

    'established' => env('FOLKS_ESTABLISHED', '2010'),

    'receipt_footer' => env(
        'FOLKS_RECEIPT_FOOTER',
        'This is a computer-generated receipt. Fees once paid are non-refundable except as per institute policy. Please retain this receipt for your records.',
    ),

    /*
    |--------------------------------------------------------------------------
    | Storage cleanup (crm:cleanup — runs daily at 03:00)
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'livewire_temp_max_age_hours' => (int) env('FOLKS_LIVEWIRE_TEMP_MAX_AGE_HOURS', 24),
    ],

    'social' => [
        'facebook' => env('FOLKS_FACEBOOK', ''),
        'instagram' => env('FOLKS_INSTAGRAM', ''),
        'youtube' => env('FOLKS_YOUTUBE', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo images (Unsplash — replace with your own campus photos later)
    |--------------------------------------------------------------------------
    */
    'images' => [
        'hero' => [
            'main' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=1920&q=80',
            'accent_one' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?auto=format&fit=crop&w=800&q=80',
            'accent_two' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=800&q=80',
            'about' => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?auto=format&fit=crop&w=900&q=80',
        ],
        'gallery' => [
            [
                'src' => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=900&q=80',
                'alt' => 'Luxury hotel reception and lobby',
                'caption' => 'Front Office & Guest Relations',
                'span' => 'sm:col-span-2 sm:min-h-[280px] lg:col-span-2 lg:row-span-2',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Fine dining restaurant service',
                'caption' => 'Food & Beverage Service',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1556910103-1c02745aae4d?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Professional hotel kitchen training',
                'caption' => 'Culinary & Kitchen Operations',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1578683010236-d716f9a04bbe?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Hotel concierge and front desk',
                'caption' => 'Concierge & Reservations',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?auto=format&fit=crop&w=900&q=80',
                'alt' => 'Travel and tourism destination',
                'caption' => 'Tourism & Travel Management',
                'span' => 'sm:col-span-2 sm:min-h-[240px] lg:col-span-2',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1631049307264-da0ec9d70304?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Luxury hotel suite and housekeeping',
                'caption' => 'Housekeeping & Room Operations',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Hotel banquet and event setup',
                'caption' => 'Events & Banquet Management',
                'span' => '',
            ],
            [
                'src' => 'https://images.unsplash.com/photo-1509042239860-f550ce710b93?auto=format&fit=crop&w=600&q=80',
                'alt' => 'Barista and coffee service training',
                'caption' => 'Coffee Shop & Bar Operations',
                'span' => '',
            ],
        ],
    ],

];
