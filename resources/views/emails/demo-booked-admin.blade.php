<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #0f172a; line-height: 1.6;">
  <h2>New demo booking — {{ $label }}</h2>
  <p>A free demo was confirmed on MentorKhoj. Please follow up on WhatsApp/phone.</p>

  <p><strong>Booking reference:</strong> {{ $booking->booking_ref }}</p>
  <p><strong>Name:</strong> {{ $booking->name }}</p>
  <p><strong>Phone:</strong> {{ $booking->phone }}</p>
  <p><strong>Email:</strong> {{ $booking->email ?: '—' }}</p>
  <p><strong>Program:</strong> {{ $label }} ({{ $booking->category }})</p>
  <p><strong>Stage:</strong> {{ $booking->stage }}</p>
  <p><strong>Subjects:</strong> {{ is_array($booking->subjects) ? implode(', ', $booking->subjects) : '—' }}</p>
  <p><strong>Source:</strong> {{ $booking->source }}</p>
  <p><strong>UTM:</strong>
    {{ $booking->utm_source }} / {{ $booking->utm_medium }} / {{ $booking->utm_campaign }} / {{ $booking->utm_content }}
  </p>
  <p><strong>Status:</strong> {{ $booking->status }} · free ₹0</p>
  <p><strong>Created:</strong> {{ $booking->created_at }}</p>

  <p style="margin-top: 20px;">Open Admin → Demo Booking to update status and notes.</p>
  <p>— MentorKhoj Team</p>
</body>
</html>
