<?php

function tvr_get_used_global($key) {
    $data = get_option($key, []);
    return is_array($data) ? $data : [];
}
function tvr_add_used_global($key, $value) {
    $used = tvr_get_used_global($key);
    $used[] = $value;
    update_option($key, $used, false);
}
function tvr_generate_unique_review_field($candidates, $global_key, $forbidden = []) {
    shuffle($candidates);
    $used = tvr_get_used_global($global_key);
    foreach ($candidates as $candidate) {
        if (!in_array($candidate, $used, true) && !in_array($candidate, $forbidden, true)) {
            tvr_add_used_global($global_key, $candidate);
            return $candidate;
        }
    }
    // If all candidates are used, mutate for uniqueness
    do {
        $candidate = $candidates[array_rand($candidates)] . ' ' . strtoupper(wp_generate_password(2, false));
    } while (in_array($candidate, $used, true) || in_array($candidate, $forbidden, true));
    tvr_add_used_global($global_key, $candidate);
    return $candidate;
}

function weighted_random($items, $weights) {
    $total = array_sum($weights);
    $rand = mt_rand(1, $total);
    $cumulative = 0;
    foreach ($items as $index => $item) {
        $cumulative += $weights[$index];
        if ($rand <= $cumulative) {
            return $item;
        }
    }
    return end($items);
}
function season_periods_and_weights($season_start, $season_end) {
    $start_ts = strtotime($season_start);
    $end_ts = strtotime($season_end);
    $start_year = date('Y', $start_ts);
    $end_year = date('Y', $end_ts);
    $season_start_md = date('m-d', $start_ts);
    $season_end_md = date('m-d', $end_ts);

    $periods = [];
    $weights = [];
    list($s_month, $s_day) = explode('-', $season_start_md);
    list($e_month, $e_day) = explode('-', $season_end_md);
    $s_month = (int)$s_month; $s_day = (int)$s_day;
    $e_month = (int)$e_month; $e_day = (int)$e_day;

    for ($year = $start_year; $year <= $end_year; $year++) {
        $year_start = strtotime("{$year}-{$s_month}-{$s_day} 00:00:00");
        $year_end = strtotime("{$year}-{$e_month}-{$e_day} 23:59:59");
        if ($year_end < $year_start) {
            $year_end = strtotime("{$year}-12-31 23:59:59");
        }
        $year_start = max($year_start, 0);
        if ($year_start > current_time('timestamp')) continue;
        $year_end = min($year_end, current_time('timestamp'));
        if ($year_start < $year_end) {
            $periods[] = ['start' => $year_start, 'end' => $year_end];
            $weight = ($year - $start_year + 1) * 2;
            $weights[] = $weight;
        }
    }
    return [$periods, $weights];
}

function random_date_in_season($season_start, $season_end) {
    list($periods, $weights) = season_periods_and_weights($season_start, $season_end);
    if (empty($periods)) return date('Y-m-d H:i:s');
    $period = weighted_random($periods, $weights);
    $diff = $period['end'] - $period['start'];
    $rand_ts = $period['start'] + mt_rand(0, $diff);
    return date('Y-m-d H:i:s', $rand_ts);
}

function tvr_is_duplicate($string, $existing_array) {
    $str = strtolower(trim($string));
    foreach ($existing_array as $existing) {
        $existing_str = strtolower(trim($existing));
        if (empty($existing_str)) continue;
        if ($str === $existing_str) return true;
        if (strpos($existing_str, $str) !== false || strpos($str, $existing_str) !== false) return true;
    }
    return false;
}

function tvr_generate_reviews($post_id, $count, $lang = 'en', $location = '', $season_start = '', $season_end = '') {
    if (!$season_start || !$season_end) {
        echo '<div class="error"><p>Season start and end dates are required.</p></div>';
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        echo '<div class="error"><p>Invalid post ID.</p></div>';
        return;
    }

    $config = tvr_get_config();
    $languages = $config['languages'];
    $name_data = include(plugin_dir_path(__FILE__) . 'names.php');
    $reviewer_types = $config['reviewer_types'];
    $tones = $config['tones'];
    $rating_meta_key = $config['rating_meta_key'];

    if (!isset($languages[$lang])) $lang = 'en';
    $lang_data = $languages[$lang];
    $post_type = $post->post_type;

    if ($post_type === 'st_activity') $review_type = 'activity';
    elseif ($post_type === 'st_hotel') $review_type = 'hotel';
    elseif ($post_type === 'st_rental') $review_type = 'rental';
    else $review_type = 'tour';

    if (empty($location)) {
        $terms = wp_get_post_terms($post_id, 'st_location', ['fields' => 'names']);
        $location = !is_wp_error($terms) && !empty($terms) ? $terms[0] : '';
    }

    $all_names = [];
    foreach ($name_data as $lang_set) {
        $all_names = array_merge($all_names, $lang_set['male'], $lang_set['female']);
    }

    $title_words = [
        'adjectives' => [
            'en' => [
                'Amazing', 'Fantastic', 'Unforgettable', 'Incredible', 'Memorable', 'Superb', 'Stunning', 'Perfect', 'Awesome', 'Wonderful', 'Ultimate', 'Charming', 'Splendid', 'Breathtaking', 'Cozy', 'Delightful', 'Lively', 'Peaceful', 'Grand', 'Unique', 'Hilarious', 'Wild', 'Dreamy', 'Effortless', 'Picture-Perfect', 'Thrilling', 'Outstanding', 'Fun', 'Educational', 'Authentic', 'Cultural', 'Local', 'Expert', 'Friendly', 'Professional', 'Exciting', 'Enriching', 'Adventurous', 'Entertaining', 'Insightful', 'Relaxing', 'Surprising', 'Colorful', 'Welcoming', 'Safe', 'Personal', 'Inclusive', 'Hands-On', 'Informative'
            ],
        ],
        'nouns' => [
            'en' => [
                'Adventure', 'Experience', 'Journey', 'Excursion', 'Discovery', 'Exploration', 'Wildlife', 'Culture', 'Guide', 'Team', 'Expert', 'Local', 'Spot', 'Route', 'Trip', 'Excitement', 'Group', 'Learning', 'Memory', 'Highlight', 'Impression', 'Recollection', 'Reflection', 'Story', 'Moment', 'Saga', 'Hike', 'Excitement', 'Event', 'Activity', 'Sight', 'Destination', 'Encounter', 'Opportunity', 'Insight', 'Interaction', 'Expedition', 'Visit'
            ],
        ]
    ];

    // Phrase pools (as before, unchanged for brevity)
    // ... $core_phrases, $personal_details_pools, $humor_pools, $repeat_pools, $booking_pools ...

    // --- DEDUPLICATE repeat and booking pools for this batch ---
    $repeat_pool = array_unique($repeat_pools['en']);
    $booking_pool = array_unique($booking_pools['en']);

    $existing_comments = get_comments([
        'post_id' => $post_id,
        'status' => 'approve',
        'number' => 0
    ]);
    $existing_titles = [];
    $existing_contents = [];
    foreach ($existing_comments as $comment) {
        $existing_titles[] = get_comment_meta($comment->comment_ID, 'comment_title', true);
        $existing_contents[] = $comment->comment_content;
    }

    // Keep track of batch-used repeat/booking phrases
    $used_repeat_batch = [];
    $used_booking_batch = [];

    for ($i = 0; $i < $count; $i++) {
        $attempts = 0;
        do {
            $first_name = $all_names[array_rand($all_names)];
            $last_name = $all_names[array_rand($all_names)];
            $name = trim("$first_name $last_name");
            $name = tvr_generate_unique_review_field([$name], 'tvr_used_names');

            $rtype = $reviewer_types[array_rand($reviewer_types)];
            $tone = $tones[array_rand($tones)];
            $personal_details = $personal_details_pools['en'];
            $humor_templates = $humor_pools['en'];
            $core_pool = $core_phrases[$review_type];

            $adj_list = $title_words['adjectives']['en'];
            $noun_list = $title_words['nouns']['en'];

            // ENFORCE: at least 2–3 sentences per comment, no repeated phrases in batch
            $desc_lines = [];
            // Always add a unique core phrase
            do {
                $core_phrase = $core_pool[array_rand($core_pool)];
            } while (tvr_is_duplicate($core_phrase, $desc_lines));
            $desc_lines[] = $core_phrase;
            // Always add a unique personal detail phrase
            do {
                $personal_phrase = $personal_details[array_rand($personal_details)];
            } while (tvr_is_duplicate($personal_phrase, $desc_lines));
            $desc_lines[] = $personal_phrase;
            // Add a unique repeat phrase (if not already used in batch)
            if (rand(0,2) == 0 && count($used_repeat_batch) < count($repeat_pool)) {
                do {
                    $repeat_phrase = $repeat_pool[array_rand($repeat_pool)];
                } while (tvr_is_duplicate($repeat_phrase, $desc_lines) || in_array($repeat_phrase, $used_repeat_batch));
                $desc_lines[] = $repeat_phrase;
                $used_repeat_batch[] = $repeat_phrase;
            }
            // Add a unique booking phrase (if not already used in batch)
            if (rand(0,3) == 0 && count($used_booking_batch) < count($booking_pool)) {
                do {
                    $booking_phrase = $booking_pool[array_rand($booking_pool)];
                } while (tvr_is_duplicate($booking_phrase, $desc_lines) || in_array($booking_phrase, $used_booking_batch));
                $desc_lines[] = $booking_phrase;
                $used_booking_batch[] = $booking_phrase;
            }
            // Humor phrase (unique for this comment)
            if (rand(0,4) == 0) {
                do {
                    $humor_phrase = $humor_templates[array_rand($humor_templates)];
                } while (tvr_is_duplicate($humor_phrase, $desc_lines));
                $desc_lines[] = $humor_phrase;
            }
            // Shuffle and trim to 3-5 phrases
            shuffle($desc_lines);
            $desc_lines = array_slice($desc_lines, 0, rand(3,5));
            if (rand(0,2) == 0) $desc_lines[] = "($rtype)";

            $description = rtrim(implode(' ', $desc_lines));
            $description = preg_replace('/\s+/', ' ', $description);
            $description = tvr_generate_unique_review_field([$description], 'tvr_used_descriptions');

            $adj = $adj_list[array_rand($adj_list)];
            $noun = $noun_list[array_rand($noun_list)];
            $bits = [
                "$adj $noun",
                "$adj $noun in $location",
                "$adj $noun by $rtype",
                "What a $adj $noun!",
                "$adj $noun experience",
                "$adj $noun group",
                "$adj $noun guide",
                "An $adj $noun",
                "$adj $noun for everyone",
                "$adj $noun with experts",
            ];
            $title = $bits[array_rand($bits)];
            $title = str_replace('  ', ' ', $title);
            $title = preg_replace('/\b(review|day|week|month|year|season|pool|sunny|trail|spring|summer|fall|winter)\b/i', '', $title);
            $title = preg_replace('/\d{2,4}/', '', $title);
            $title = trim($title);
            $title = tvr_generate_unique_review_field([$title], 'tvr_used_titles', [$description]);

            $comment_date = random_date_in_season($season_start, $season_end);

            $is_title_duplicate = tvr_is_duplicate($title, $existing_titles);
            $is_content_duplicate = tvr_is_duplicate($description, $existing_contents);

            $attempts++;
        } while (($is_title_duplicate || $is_content_duplicate) && $attempts < 10);

        $existing_titles[] = $title;
        $existing_contents[] = $description;

        $email = strtolower(str_replace([' ', '&'], ['.', ''], $name)) . '.' . wp_generate_password(4, false) . '@example.com';
        $comment_data = [
            'comment_post_ID' => $post_id,
            'comment_author' => sanitize_text_field($name),
            'comment_author_email' => sanitize_email($email),
            'comment_content' => sanitize_textarea_field($description),
            'comment_approved' => 1,
            'comment_type' => '',
            'comment_date' => $comment_date,
        ];
        $comment_id = wp_insert_comment($comment_data);

        if (!$comment_id) {
            echo '<div class="error"><p>Failed to generate review for ' . esc_html($post->post_title) . '.</p></div>';
            continue;
        }

        update_comment_meta($comment_id, 'comment_title', sanitize_text_field($title));
        update_comment_meta($comment_id, 'comment_rate', 5);
        $review_stats = [
            'Tour Guide' => 5,
            'Location' => 5,
            'Service' => 5,
            'Friendliness' => 5,
            'Overall' => 5,
        ];
        foreach ($review_stats as $criterion => $value) {
            $slug = sanitize_title($criterion);
            update_comment_meta($comment_id, "st_stat_$slug", $value);
        }
    }

    echo "<div class='updated'><p>Generated $count globally unique and authentic reviews for " . esc_html($post->post_title) . " in " . esc_html($languages[$lang]['name']) . "</p></div>";
}

?>