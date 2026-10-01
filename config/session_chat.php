<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MentorKhoj ADMIN / Support chat mentor
    |--------------------------------------------------------------------------
    | Synthetic mentor profile used for admin→student session chat threads.
    | Display name shown to students is "ADMIN". Marketplace listing stays unpublished.
    */
    'support_mentor_username' => env('SESSION_CHAT_SUPPORT_USERNAME', 'mentorkhoj-support'),
    'support_mentor_display_name' => env('SESSION_CHAT_SUPPORT_DISPLAY_NAME', 'ADMIN'),
    'support_mentor_id' => env('SESSION_CHAT_SUPPORT_MENTOR_ID')
        ? (int) env('SESSION_CHAT_SUPPORT_MENTOR_ID')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Paid messaging window (add-on send gate)
    |--------------------------------------------------------------------------
    | After pack grant / paid session / paid_session_done, both sides may send
    | for this many days unless a planned session exists or admin force-enables.
    */
    'messaging_window_days' => (int) env('SESSION_CHAT_MESSAGING_WINDOW_DAYS', 7),

    /*
    | Admin WhatsApp shown when messaging is disabled (purchase / renew CTA).
    | Digits with or without country code; default is MentorKhoj support.
    */
    'admin_whatsapp' => env('SESSION_CHAT_ADMIN_WHATSAPP', env('INVOICE_WHATSAPP_PHONE', '7366939888')),
    'admin_whatsapp_display' => env('SESSION_CHAT_ADMIN_WHATSAPP_DISPLAY', '+91 73669 39888'),

    /*
    | Base CTA; admin WhatsApp line is appended in SessionChatLogic::messagingDisabledMessage().
    | Override fully via SESSION_CHAT_MESSAGING_DISABLED_MESSAGE if needed.
    */
    'messaging_disabled_message' => env('SESSION_CHAT_MESSAGING_DISABLED_MESSAGE'),
];
