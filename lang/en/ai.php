<?php

return [
    'text' => [
        'profile_bio' => 'Write a short profile bio to post on a paid social media network called :siteName.',
        'post'        => 'Write a short post for my profile on a paid social media network called :siteName.',
        'stream'      => 'Write a short stream title for my profile on a paid social media network called :siteName.',
        'story'       => 'Write a short story text/caption for :siteName. Keep it punchy and under 1–2 sentences.',
        'reel'        => 'Write a short reel caption for :siteName. Keep it punchy, natural, and under 1–2 sentences.',
    ],

    'images' => [
        'avatar' => 'Photorealistic profile avatar portrait. Centered face, friendly expression, clean background, soft studio lighting, high detail. No text, no logos, no watermark.',
        'cover'  => 'Wide cinematic cover background image for a user profile. Modern aesthetic, soft gradient lighting, subtle texture, high quality. No people, no text, no logos, no watermark.',
    ],

    'prompt' => [
        'no_explanations' => 'No explanations. Output only the final text.',
        'no_quotes'       => 'Do not wrap the text in quotes.',

        'context'         => 'Context: :type.',
        'rules_label'     => 'Rules:',
        'profile_label'   => 'Profile:',

        'tone'            => 'Tone: :tone.',
        'length_short'    => 'Length: 1–2 sentences.',
        'length_medium'   => 'Length: 2–4 sentences.',

        'avoid_prices'    => 'Avoid prices.',
        'avoid_links'     => 'Avoid external links.',
        'avoid_hashtags'  => 'Avoid hashtags.',
        'allow_hashtags'  => 'You may include 0–3 relevant hashtags.',

        'keywords'        => 'Keywords: :keywords. Use as inspiration only.',

        'profile_name'     => 'Name: :name.',
        'profile_pronouns' => 'Pronouns: :pronouns.',
        'profile_location' => 'Location: :location.',

        // images
        'mood'            => 'Mood: :tone.',
        'adult_subject'   => 'Adult subject.',
    ],
];
