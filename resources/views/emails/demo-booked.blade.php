<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #0f172a; line-height: 1.6;">
  <h2>Demo booked — {{ $label }}</h2>
  <p>Hi {{ $booking->name }},</p>
  <p>Your free demo for <strong>{{ $label }}</strong> is confirmed.</p>

  <p><strong>Booking reference:</strong> {{ $booking->booking_ref }}</p>
  <p><strong>Program:</strong> {{ $label }} Free Demo</p>
  <p><strong>Mobile:</strong> {{ $booking->phone }}</p>
  @if($booking->email)
  <p><strong>Email:</strong> {{ $booking->email }}</p>
  @endif
  @if($booking->stage)
  <p><strong>Stage:</strong> {{ $booking->stage }}</p>
  @endif

  <p style="color: #059669;"><strong>100% FREE</strong> — no payment required.</p>

  <p>We'll contact you on WhatsApp shortly to schedule your demo call.</p>
  <p>— MentorKhoj Team</p>
</body>
</html>
