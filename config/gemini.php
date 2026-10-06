<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gemini API Configuration
    |--------------------------------------------------------------------------
    */

    'api_key' => env('GEMINI_API_KEY'),
    
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash-exp'),
    
    'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
    
    'max_tokens' => 2048,
    
    'temperature' => 0.7,
    
    'timeout' => 30,
];
