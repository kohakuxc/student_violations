<?php
if (!function_exists('normalizeFreeText')) {
    function normalizeFreeText($input)
    {
        $text = strip_tags((string) $input);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }
}

if (!function_exists('detectSelfHarmContent')) {
    function detectSelfHarmContent($text)
    {
        $patterns = [
            '/suicid(e|al)/i',
            '/self[\s-]?harm/i',
            '/kill myself/i',
            '/end my life/i',
            '/hurt myself/i',
            '/cut myself/i',
            '/overdose/i',
            '/die by suicide/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('isGibberishText')) {
    function isGibberishText($text)
    {
        $letters = preg_match_all('/[a-z]/i', $text);
        if ($letters < 12) {
            return false;
        }
        $vowels = preg_match_all('/[aeiou]/i', $text);
        $vowelRatio = $vowels / max(1, $letters);
        if ($vowelRatio < 0.2) {
            return true;
        }
        $compact = strtolower(preg_replace('/\s+/', '', $text));
        if ($compact !== '' && strlen($compact) >= 12) {
            $uniqueChars = count(array_unique(str_split($compact)));
            if ($uniqueChars <= 3) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('validateFreeText')) {
    function validateFreeText($input, $minLength = 10, $maxLength = 1000)
    {
        $normalized = normalizeFreeText($input);
        $length = mb_strlen($normalized);

        if ($length === 0) {
            return ['valid' => false, 'message' => 'Description is required.'];
        }
        if ($length < $minLength) {
            return ['valid' => false, 'message' => "Description must be at least {$minLength} characters."];
        }
        if ($length > $maxLength) {
            return ['valid' => false, 'message' => "Description cannot exceed {$maxLength} characters."];
        }
        if (preg_match('/^\d+$/', $normalized)) {
            return ['valid' => false, 'message' => 'Description cannot be numbers only.'];
        }
        if (preg_match('/(.)\1{6,}/u', $normalized)) {
            return ['valid' => false, 'message' => 'Description contains excessive repeated characters.'];
        }
        if (preg_match('/\b(\w+)(?:\s+\1){2,}\b/i', $normalized)) {
            return ['valid' => false, 'message' => 'Description contains excessive repeated words.'];
        }
        if (isGibberishText($normalized)) {
            return ['valid' => false, 'message' => 'Description appears to be gibberish. Please provide more detail.'];
        }

        return [
            'valid' => true,
            'value' => $normalized,
            'self_harm' => detectSelfHarmContent($normalized),
        ];
    }
}
