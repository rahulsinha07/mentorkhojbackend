@php
    $canonical = 'https://aihealtheval.com/arvind-pandey-ips-bihar';
    $officialHub = 'https://creativepolicing.yoga';
    $officialProfile = 'https://creativepolicing.yoga/hi/arvind-pandey-ips-bihar';
    $officialProfileEn = 'https://creativepolicing.yoga/en/arvind-pandey-ips-bihar';
    $upscMentor = 'https://www.mentorkhoj.com/mentor/arvind-pandey-ips';
    $portraitRel = 'public/assets/arvind/arvind-pandey-ips-bihar-portrait.jpg';
    $portrait = asset($portraitRel);
    $pageTitle = 'अरविंद पांडेय आईपीएस बिहार | MentorKhoj · AI Health Eval';
    $pageDescription = 'अरविंद पांडेय आईपीएस बिहार (1988 बैच, पूर्व डीजीपी सह नागरिक सुरक्षा आयुक्त) — योग, सामुदायिक स्वास्थ्य और रचनात्मक पुलिसिंग पर MentorKhoj / AI Health Eval प्रोफ़ाइल। आधिकारिक जीवनी: creativepolicing.yoga';
    $faqs = [
        [
            'q' => 'aihealtheval.com पर अरविंद पांडेय आईपीएस बिहार की यह पेज क्यों है?',
            'a' => 'यह MentorKhoj / AI Health Eval पर उनकी योग-अनुशासन और सामुदायिक स्वास्थ्य दृष्टि की प्रोफ़ाइल है। पूर्ण आधिकारिक जीवनी और सेवा-यात्रा creativepolicing.yoga पर है।',
        ],
        [
            'q' => 'अरविंद पांडेय आईपीएस बिहार की आधिकारिक वेबसाइट कौन सी है?',
            'a' => 'आधिकारिक व्यक्तिगत ब्रांड साइट https://creativepolicing.yoga है। यह MentorKhoj पेज उसी व्यक्ति की स्वास्थ्य-योग प्रोफ़ाइल है, जीवनी की नकल नहीं।',
        ],
        [
            'q' => 'अरविंद पांडेय IPS किस बैच के हैं और अंतिम पद क्या था?',
            'a' => '1988 बैच, बिहार कैडर IPS। जनवरी 2019 से जून 2023 तक DGP cum Civil Defence Commissioner, बिहार।',
        ],
        [
            'q' => 'रचनात्मक पुलिसिंग का स्वास्थ्य से क्या संबंध है?',
            'a' => 'Creative Policing समुदाय को दंड नहीं, सहभागिता से जोड़ती है — युवा अनुशासन, योग और जनस्वास्थ्य वही दृष्टि MentorKhoj पर लाती है।',
        ],
        [
            'q' => 'Who is Arvind Pandey IPS Bihar on MentorKhoj?',
            'a' => 'A retired 1988-batch Bihar cadre IPS officer and former DGP cum Civil Defence Commissioner (2019–2023). This page covers his yoga and community-health voice on MentorKhoj; the official biography is at creativepolicing.yoga.',
        ],
    ];
    $personJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        '@id' => $canonical . '#person',
        'name' => 'Arvind Pandey',
        'alternateName' => [
            'अरविंद पांडेय',
            'अरविन्द पाण्डेय',
            'Arvind Pandey IPS Bihar',
            'IPS Arvind Pandey',
            'DGP Arvind Pandey',
            'DG Arvind Pandey Bihar',
            'अरविंद पांडेय आईपीएस बिहार',
        ],
        'honorificSuffix' => 'IPS',
        'jobTitle' => [
            'Former DGP cum Civil Defence Commissioner of Bihar',
            'Retired IPS Officer (1988 Bihar Cadre)',
        ],
        'description' => 'Arvind Pandey IPS Bihar — retired 1988-batch Bihar cadre IPS officer; yoga, community health, and Creative Policing voice on MentorKhoj / AI Health Eval.',
        'url' => $canonical,
        'image' => $portrait,
        'sameAs' => [
            $officialHub,
            $officialProfile,
            $officialProfileEn,
            $upscMentor,
            'https://www.linkedin.com/in/ipsarvindpandey',
        ],
        'knowsAbout' => [
            'Yoga',
            'Community health',
            'Creative Policing',
            'Indian Police Service',
            'MentorKhoj',
        ],
        'homeLocation' => [
            '@type' => 'Place',
            'name' => 'Patna, Bihar, India',
        ],
    ];
    $webPageJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ProfilePage',
        '@id' => $canonical . '#webpage',
        'url' => $canonical,
        'name' => $pageTitle,
        'description' => $pageDescription,
        'inLanguage' => ['hi-IN', 'en-IN'],
        'isPartOf' => [
            '@type' => 'WebSite',
            'name' => 'MentorKhoj · AI Health Eval',
            'url' => 'https://aihealtheval.com',
        ],
        'about' => ['@id' => $canonical . '#person'],
        'mainEntity' => ['@id' => $canonical . '#person'],
        'relatedLink' => [$officialProfile, $officialHub, $upscMentor],
        'primaryImageOfPage' => [
            '@type' => 'ImageObject',
            'url' => $portrait,
        ],
    ];
    $faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static function ($faq) {
            return [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ];
        }, $faqs),
    ];
@endphp
<!DOCTYPE html>
<html lang="hi-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="keywords" content="Arvind Pandey IPS Bihar, अरविंद पांडेय आईपीएस बिहार, DGP Arvind Pandey, Creative Policing Yoga, MentorKhoj, AI Health Eval">
    <meta name="author" content="MentorKhoj">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
    <link rel="canonical" href="{{ $canonical }}">
    <link rel="alternate" hreflang="hi" href="{{ $canonical }}">
    <link rel="alternate" hreflang="en" href="{{ $canonical }}">
    <link rel="alternate" hreflang="x-default" href="{{ $canonical }}">
    <meta property="og:type" content="profile">
    <meta property="og:locale" content="hi_IN">
    <meta property="og:locale:alternate" content="en_IN">
    <meta property="og:site_name" content="MentorKhoj · AI Health Eval">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $portrait }}">
    <meta property="profile:first_name" content="Arvind">
    <meta property="profile:last_name" content="Pandey">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $pageDescription }}">
    <meta name="twitter:image" content="{{ $portrait }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('public/favicon.ico') }}">
    <script type="application/ld+json">{!! json_encode($personJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <script type="application/ld+json">{!! json_encode($webPageJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <style>
        :root {
            --ink: #1c140f;
            --muted: #5c4e44;
            --sand: #f4eee6;
            --card: #fffdf9;
            --line: #e4d8cc;
            --brand: #6b2d1a;
            --accent: #c45c26;
            --deep: #2a1810;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, serif;
            color: var(--ink);
            background: var(--sand);
            line-height: 1.65;
        }
        a { color: var(--brand); }
        header {
            background: var(--deep);
            color: #f8efe6;
            padding: 1rem 1.25rem;
        }
        header .brand {
            font-size: 0.78rem;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: #e8c9a8;
            margin: 0;
        }
        header .eyebrow {
            margin: 0.35rem 0 0;
            font-size: 0.9rem;
            color: #f3d7b8;
        }
        .wrap { max-width: 44rem; margin: 0 auto; padding: 0 1.25rem 3.5rem; }
        .hero {
            display: grid;
            gap: 1.25rem;
            padding: 1.75rem 0 0.5rem;
        }
        @media (min-width: 720px) {
            .hero { grid-template-columns: 200px 1fr; align-items: end; }
        }
        .hero img {
            width: 100%;
            max-width: 220px;
            aspect-ratio: 4 / 5;
            object-fit: cover;
            object-position: top;
            border: 1px solid var(--line);
            box-shadow: 0 16px 36px rgba(42, 24, 16, 0.18);
            background: var(--deep);
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: clamp(1.7rem, 4vw, 2.35rem);
            line-height: 1.2;
            color: var(--deep);
        }
        .lead { margin: 0; color: var(--muted); font-size: 1.05rem; }
        .official {
            display: inline-block;
            margin-top: 1rem;
            margin-right: 0.6rem;
            padding: 0.7rem 0.95rem;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
        }
        .official:hover { border-color: var(--accent); }
        section { margin-top: 2rem; }
        h2 {
            margin: 0 0 0.7rem;
            font-size: 1.35rem;
            color: var(--deep);
        }
        p { margin: 0 0 0.85rem; }
        .en { color: var(--muted); font-size: 0.97rem; }
        article {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 1rem 1.1rem;
            margin-bottom: 0.75rem;
        }
        article h3 { margin: 0 0 0.4rem; font-size: 1.02rem; }
        article p { margin: 0; font-size: 0.95rem; color: var(--muted); }
        footer {
            margin-top: 2.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--line);
            font-size: 0.88rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <header>
        <p class="brand">MentorKhoj · AI Health Eval</p>
        <p class="eyebrow">योग · सामुदायिक स्वास्थ्य · रचनात्मक पुलिसिंग</p>
    </header>
    <main class="wrap">
        <div class="hero">
            <img src="{{ $portrait }}" width="900" height="1125" alt="अरविंद पांडेय आईपीएस बिहार — आधिकारिक चित्र">
            <div>
                <h1>अरविंद पांडेय आईपीएस बिहार</h1>
                <p class="lead">सेवानिवृत्त 1988 बैच बिहार कैडर IPS अधिकारी। MentorKhoj / AI Health Eval पर यह पेज उनकी योग-साधना और सामुदायिक स्वास्थ्य दृष्टि के लिए है — पूर्ण जीवनी आधिकारिक साइट पर है।</p>
                <a class="official" href="{{ $canonical }}">MentorKhoj / AI Health Eval प्रोफ़ाइल: aihealtheval.com/arvind-pandey-ips-bihar</a>
                <a class="official" href="{{ $upscMentor }}">MentorKhoj पर निःशुल्क UPSC मेंटरशिप: mentorkhoj.com/mentor/arvind-pandey-ips</a>
            </div>
        </div>

        <section>
            <h2>कौन हैं अरविंद पांडेय IPS Bihar?</h2>
            <p>अरविंद पांडेय आईपीएस बिहार 1988 बैच के भारतीय पुलिस सेवा अधिकारी हैं। जनवरी 2019 से जून 2023 तक वे बिहार के DGP cum Civil Defence Commissioner रहे। सेवा के लगभग चार दशक के बाद वे सेवानिवृत्त IPS अधिकारी हैं।</p>
            <p class="en">Arvind Pandey IPS Bihar is a retired 1988-batch Bihar cadre officer and former DGP cum Civil Defence Commissioner (2019–2023). Career dates, postings, and Creative Policing history live on his official hub — this MentorKhoj page is not a second biography.</p>
            <p>जीवनी, पदस्थापन और Creative Policing Yoga: <a href="{{ $officialProfile }}">{{ $officialProfile }}</a></p>
        </section>

        <section>
            <h2>यह पेज MentorKhoj पर क्यों है?</h2>
            <p>AI Health Eval / MentorKhoj शिक्षा, अनुशासन और स्वास्थ्य-मार्गदर्शन का मंच है। अरविंद पांडेय की सार्वजनिक दृष्टि योग को केवल आसन नहीं, आंतरिक अनुशासन मानती है — वही अनुशासन लोकसेवा और सामुदायिक स्वास्थ्य को स्थिर रखता है।</p>
            <p>यहाँ उद्देश्य रैंक के लिए जीवनी दोहराना नहीं है। उद्देश्य यह दिखाना है कि सेवानिवृत्त IPS नेतृत्व योग, युवा अनुशासन और जनकल्याण को कैसे जोड़ता है — MentorKhoj के स्वास्थ्य-मूल्यांकन संदर्भ में।</p>
            <p class="en">This isolated page exists so MentorKhoj can host a health-and-yoga profile of Arvind Pandey IPS Bihar without touching admin, payments, or API flows. It complements — it does not replace — creativepolicing.yoga.</p>
        </section>

        <section>
            <h2>रचनात्मक पुलिसिंग और सामुदायिक स्वास्थ्य</h2>
            <p>Creative Policing का सार दंड नहीं, समुदाय-निर्माण है: विवाद का शांतिपूर्ण हल, युवाओं की भागीदारी, और अपराध की जड़ों तक पहुँच। वही सोच स्वास्थ्य में अनुवाद होती है — नशा-मुक्ति संदेश, योग-दिनचर्या, और जनजागरूकता।</p>
            <p class="en">Community-rooted policing and yoga practice share one idea: discipline that serves the public. MentorKhoj frames that idea for learners and mentors; the official Creative Policing Yoga site remains the source of record.</p>
        </section>

        <section>
            <h2>अक्सर पूछे जाने वाले प्रश्न</h2>
            @foreach ($faqs as $faq)
                <article>
                    <h3>{{ $faq['q'] }}</h3>
                    <p>{{ $faq['a'] }}</p>
                </article>
            @endforeach
        </section>

        <footer>
            <p>MentorKhoj स्वास्थ्य प्रोफ़ाइल · <a href="{{ $canonical }}">{{ $canonical }}</a></p>
            <p>आधिकारिक व्यक्तिगत ब्रांड: <a href="{{ $officialHub }}">{{ $officialHub }}</a> · English profile: <a href="{{ $officialProfileEn }}">{{ $officialProfileEn }}</a></p>
            <p>Free UPSC mentor session: <a href="{{ $upscMentor }}">{{ $upscMentor }}</a></p>
        </footer>
    </main>
</body>
</html>
