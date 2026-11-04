<?php

/**
 * Mentor Service Agreement — legal terms template.
 * Placeholders: {platform_fee_percent}, {mentor_name}, {upi_id}, {invoice_number}, {agreement_date}
 */
return [
    'version' => '2026-03-01',
    'title' => 'Mentor Service Agreement',

    'payout_note' => 'Mentor payout is released only after each session is marked completed on MentorKhoj.',

    'payout_pending_note' => 'Payout details pending — mentor must complete Payouts setup on the MentorKhoj dashboard.',

    'clauses' => [
        [
            'heading' => '1. Parties and Platform Role',
            'body' => 'This Mentor Service Agreement ("Agreement") is entered into between BetterWits Software Private Limited, operating the MentorKhoj platform ("Platform", "we", "us"), and {mentor_name} ("Mentor", "you"). MentorKhoj acts solely as an intermediary marketplace connecting students with mentors. The Platform facilitates discovery, booking, payment collection, and session management but does not employ the Mentor.',
        ],
        [
            'heading' => '2. Linked Customer Invoice',
            'body' => 'This Agreement relates to mentorship services referenced under customer invoice {invoice_number}, dated {agreement_date}. Commercial terms below reflect the gross session value attributable to the Mentor for the line items covered by this Agreement.',
        ],
        [
            'heading' => '3. Platform Fee',
            'body' => 'The Platform charges a service fee of {platform_fee_percent}% on the gross value of each completed mentorship session before payout. This fee covers payment processing, platform infrastructure, customer support, and marketplace operations. The fee is deducted automatically; the Mentor receives the net amount only.',
        ],
        [
            'heading' => '4. Payout Terms',
            'body' => 'Mentor payouts are made to the UPI ID or bank account registered on the Mentor\'s MentorKhoj profile ({upi_id}). Payouts are initiated only after each session is verified as completed on the Platform. The Mentor must keep payout details accurate and current. The Platform is not liable for failed transfers due to incorrect payout information supplied by the Mentor.',
        ],
        [
            'heading' => '5. Mentor Obligations',
            'body' => 'The Mentor agrees to: (a) maintain an accurate, professional public profile; (b) deliver sessions professionally and on schedule; (c) not solicit or accept off-platform payments from students introduced through MentorKhoj; (d) comply with applicable laws and professional standards; (e) respond promptly to booking and messaging communications through the Platform.',
        ],
        [
            'heading' => '6. Tax and Compliance',
            'body' => 'The Mentor is solely responsible for reporting and remitting all applicable taxes, including income tax and GST if applicable, on amounts received. The Platform does not withhold taxes on behalf of the Mentor unless required by law.',
        ],
        [
            'heading' => '7. Confidentiality and Data Protection',
            'body' => 'Both parties shall protect confidential information shared during mentorship engagements. The Mentor shall handle student personal data in accordance with applicable privacy laws and MentorKhoj\'s privacy policy. Session content and student information must not be disclosed to third parties without consent.',
        ],
        [
            'heading' => '8. Limitation of Liability',
            'body' => 'To the maximum extent permitted by law, the Platform\'s liability under this Agreement is limited to the net payout amount due for the specific sessions covered. The Platform is not liable for indirect, incidental, or consequential damages arising from mentorship sessions or Mentor conduct.',
        ],
        [
            'heading' => '9. Governing Law',
            'body' => 'This Agreement is governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of courts in Patna, Bihar.',
        ],
        [
            'heading' => '10. Electronic Acceptance',
            'body' => 'By continuing to offer services on MentorKhoj and accepting bookings, the Mentor acknowledges and agrees to these terms. This Agreement may be updated by the Platform with reasonable notice; continued use constitutes acceptance of revised terms.',
        ],
    ],
];
