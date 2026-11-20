<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Real-images-only product photography
    |--------------------------------------------------------------------------
    |
    | Approval needs a second staff member (four-eyes) unless explicitly disabled. AVIF renditions
    | are generated only where the server's GD build supports them; JPEG is always generated and
    | WebP whenever available.
    |
    */

    'require_second_reviewer' => (bool) env('MEDIA_REQUIRE_SECOND_REVIEWER', true),

    'avif' => (bool) env('MEDIA_AVIF', true),

];
