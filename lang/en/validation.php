<?php

// Common validation messages in English (the app UI is English for every locale).
return [
    'array' => 'The :attribute must be a list.',
    'boolean' => 'The :attribute must be true or false.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'date' => 'The :attribute must be a valid date.',
    'email' => 'The :attribute must be a valid email address.',
    'exists' => 'The selected :attribute is invalid.',
    'in' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute must be a whole number.',
    'numeric' => 'The :attribute must be a number.',
    'required' => 'The :attribute is required.',
    'string' => 'The :attribute must be text.',
    'unique' => 'The :attribute has already been taken.',
    'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
    'min' => [
        'numeric' => 'The :attribute must be at least :min.',
        'string' => 'The :attribute must be at least :min characters.',
        'array' => 'The :attribute must have at least :min items.',
    ],
    'max' => [
        'numeric' => 'The :attribute must not be greater than :max.',
        'string' => 'The :attribute must not be longer than :max characters.',
        'array' => 'The :attribute must not have more than :max items.',
    ],
    'attributes' => [],
    'custom' => [],
];
