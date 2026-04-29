<?php

return [
    'required' => 'The :attribute field is required.',
    'numeric' => 'The :attribute must be a number.',
    'max' => [
        'string' => 'The :attribute may not be greater than :max characters.',
    ],
    'custom' => [
        'title' => [
            'required' => 'Product title is required.',
        ],
        'price' => [
            'required' => 'Product price is required.',
        ],
    ],
    'attributes' => [
        'title' => 'Title',
        'price' => 'Price',
    ],
];
