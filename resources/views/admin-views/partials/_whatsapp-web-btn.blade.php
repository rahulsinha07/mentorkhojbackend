@if(!empty($url))
    <a class="btn btn-success admin-wa-btn {{ $class ?? '' }}"
       href="{{ $url }}"
       target="_blank"
       rel="noopener noreferrer"
       title="{{ $title ?? 'Open in WhatsApp' }}"
       aria-label="{{ $title ?? 'Open in WhatsApp' }}">
        <i class="tio-whatsapp" aria-hidden="true"></i>
        @if(!empty($label))
            <span class="admin-wa-btn__label">{{ $label }}</span>
        @endif
    </a>
@endif
