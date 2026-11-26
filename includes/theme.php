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
