<?php
// includes/theme.php - Dynamic Brand Theme, Logo, & Pastel Palette Engine

if (!function_exists('get_brand_theme')) {
    function get_brand_theme($set) {
        return [
            'nama_laundry' => !empty($set['nama_laundry']) ? $set['nama_laundry'] : 'Laundry Ar-Roniyah',
            'tagline'      => !empty($set['tagline']) ? $set['tagline'] : 'Solusi Laundry Anda',
            'alamat'       => !empty($set['alamat']) ? $set['alamat'] : 'Jln. Raya Pelabuhan Kupal',
            'whatsapp'     => !empty($set['whatsapp']) ? $set['whatsapp'] : '081253996394',
            'instagram'    => !empty($set['instagram']) ? $set['instagram'] : '@laundry_ar-roniyah',
            'logo_data'    => $set['logo_data'] ?? '',
            'logo_icon'    => !empty($set['logo_icon']) ? $set['logo_icon'] : 'fa-soap',
            'primary'      => !empty($set['theme_primary']) ? $set['theme_primary'] : '#DB2777',
            'pastel'       => !empty($set['theme_pastel']) ? $set['theme_pastel'] : '#FFCED8',
            'secondary'    => !empty($set['theme_secondary']) ? $set['theme_secondary'] : '#FFE7B8',
            'accent'       => !empty($set['theme_accent']) ? $set['theme_accent'] : '#701A45',
            'bg'           => !empty($set['theme_bg']) ? $set['theme_bg'] : '#FFF9FA',
        ];
    }
}

if (!function_exists('render_theme_styles')) {
    function render_theme_styles($theme) {
        $p   = htmlspecialchars($theme['primary']);
        $pas = htmlspecialchars($theme['pastel']);
        $sec = htmlspecialchars($theme['secondary']);
        $acc = htmlspecialchars($theme['accent']);
        $bg  = htmlspecialchars($theme['bg']);

        return <<<CSS
<style id="dynamic-brand-theme">
    :root {
        --brand-primary: {$p};
        --brand-pastel: {$pas};
        --brand-secondary: {$sec};
        --brand-accent: {$acc};
        --brand-bg: {$bg};
    }
    body.themed-body {
        background-color: var(--brand-bg);
    }
    .brand-gradient-hero {
        background: linear-gradient(135deg, var(--brand-accent) 0%, var(--brand-primary) 55%, var(--brand-pastel) 100%);
    }
    .brand-gradient-soft {
        background: linear-gradient(135deg, var(--brand-pastel) 0%, var(--brand-secondary) 100%);
    }
    .brand-gradient-sidebar {
        background: linear-gradient(180deg, #1E1321 0%, var(--brand-accent) 100%);
    }
    .btn-brand {
        background-color: var(--brand-primary);
        color: #ffffff;
        transition: all 0.2s ease;
    }
    .btn-brand:hover {
        filter: brightness(0.92);
        transform: translateY(-1px);
    }
    .text-brand {
        color: var(--brand-primary);
    }
    .border-brand {
        border-color: var(--brand-primary);
    }
    .bg-brand-pastel {
        background-color: var(--brand-pastel);
    }
    .bg-brand-secondary {
        background-color: var(--brand-secondary);
    }
    .badge-tagline {
        background-color: var(--brand-secondary);
        color: var(--brand-accent);
    }

    /* Night-Shift Dark Mode Support */
    body.dark-mode {
        background-color: #0f172a !important;
        color: #e2e8f0 !important;
    }
    body.dark-mode #top-bar,
    body.dark-mode .bg-white,
    body.dark-mode .bg-white\/90 {
        background-color: #1e293b !important;
        color: #f1f5f9 !important;
        border-color: #334155 !important;
    }
    body.dark-mode .bg-slate-50,
    body.dark-mode .bg-slate-100,
    body.dark-mode .bg-slate-50\/80,
    body.dark-mode .bg-slate-100\/80,
    body.dark-mode .bg-slate-50\/75,
    body.dark-mode .bg-indigo-50,
    body.dark-mode .bg-indigo-50\/70,
    body.dark-mode .bg-pink-50,
    body.dark-mode .bg-pink-50\/50 {
        background-color: #0f172a !important;
        border-color: #334155 !important;
    }
    body.dark-mode .hover\:bg-slate-50:hover,
    body.dark-mode .hover\:bg-slate-50\/70:hover,
    body.dark-mode .hover\:bg-indigo-50\/30:hover {
        background-color: #293548 !important;
    }
    body.dark-mode .border-slate-100,
    body.dark-mode .border-slate-200,
    body.dark-mode .border-slate-200\/80,
    body.dark-mode .divide-slate-100 > * {
        border-color: #334155 !important;
    }
    body.dark-mode .text-slate-800,
    body.dark-mode .text-slate-900,
    body.dark-mode .text-slate-700 {
        color: #f1f5f9 !important;
    }
    body.dark-mode .text-slate-500,
    body.dark-mode .text-slate-600 {
        color: #94a3b8 !important;
    }
    body.dark-mode .text-indigo-600,
    body.dark-mode .text-indigo-700 {
        color: #f472b6 !important;
    }
    body.dark-mode .bg-rose-50,
    body.dark-mode .bg-rose-50\/60 {
        background-color: rgba(159, 18, 57, 0.25) !important;
        border-color: #be123c !important;
    }
    body.dark-mode .bg-amber-50,
    body.dark-mode .bg-emerald-50 {
        background-color: rgba(30, 41, 59, 0.9) !important;
    }
    body.dark-mode .text-amber-900,
    body.dark-mode .text-emerald-900,
    body.dark-mode .text-rose-900 {
        color: #f8fafc !important;
    }
    body.dark-mode input,
    body.dark-mode select,
    body.dark-mode textarea {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        border-color: #334155 !important;
    }
</style>
CSS;
    }
}

if (!function_exists('render_brand_logo_html')) {
    function render_brand_logo_html($theme, $boxClass = 'w-10 h-10 rounded-2xl', $iconClass = 'text-lg') {
        if (!empty($theme['logo_data'])) {
            $src = htmlspecialchars($theme['logo_data']);
            $alt = htmlspecialchars($theme['nama_laundry']);
            return "<div class=\"{$boxClass} overflow-hidden bg-white shadow-md flex items-center justify-center shrink-0 border border-white/40\"><img src=\"{$src}\" alt=\"{$alt}\" class=\"w-full h-full object-cover\"></div>";
        }
        $icon = htmlspecialchars($theme['logo_icon']);
        return "<div class=\"{$boxClass} text-white flex items-center justify-center font-bold shadow-md shrink-0\" style=\"background: linear-gradient(135deg, var(--brand-primary), var(--brand-accent));\"><i class=\"fas {$icon} {$iconClass}\"></i></div>";
    }
}
?>
