<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Student session GST (percent)
    |--------------------------------------------------------------------------
    | Applied only on paid mentor-session bookings for student checkout.
    | Stored in mentor_bookings.tax_amount. Mentor net / platform fee stay on
    | pre-GST amount. Not shown on mentor-facing UI.
    */
    'gst_percent' => (float) env('MENTOR_SESSION_GST_PERCENT', 18),
];
