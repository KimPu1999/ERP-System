<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'unique' => 'The :attribute has already been taken.',
    'exists' => 'The selected :attribute is invalid.',
    'confirmed' => 'The :attribute confirmation does not match.',
    'string' => 'The :attribute must be a string.',
    'array' => 'The :attribute must be an array.',
    'boolean' => 'The :attribute field must be true or false.',
    'date' => 'The :attribute is not a valid date.',
    'date_format' => 'The :attribute does not match the format :format.',
    'integer' => 'The :attribute must be an integer.',
    'numeric' => 'The :attribute must be a number.',
    'url' => 'The :attribute format is invalid.',
    'uuid' => 'The :attribute must be a valid UUID.',
    'in' => 'The selected :attribute is invalid.',
    'max' => [
        'string' => 'The :attribute may not be greater than :max characters.',
        'array' => 'The :attribute may not have more than :max items.',
    ],
    'min' => [
        'string' => 'The :attribute must be at least :min characters.',
    ],
    'attributes' => [
        'email' => 'email address',
        'password' => 'password',
        'name' => 'name',
    ],
];
