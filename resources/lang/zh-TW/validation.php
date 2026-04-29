<?php

return [
    'required' => ':attribute 欄位必填',
    'numeric' => ':attribute 必須是數字',
    'max' => [
        'string' => ':attribute 最多 :max 個字',
    ],
    'custom' => [
        'title' => [
            'required' => '商品標題必填',
        ],
        'price' => [
            'required' => '商品價格必填',
        ],
    ],
    'attributes' => [
        'title' => '標題',
        'price' => '價格',
    ],
];
