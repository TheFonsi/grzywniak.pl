<?php
declare(strict_types=1);

function adminShellStyles(): string {
    return <<<'CSS'
<style>
.admin-shell{position:relative;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:14px 24px;border-bottom:1px solid #2b3954;background:#0c1423;color:#eaf0ff;font:13px/1.35 system-ui,sans-serif;min-height:68px}.admin-shell *{box-sizing:border-box}.admin-shell-brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:#f0f4ff;font-size:14px;font-weight:800;white-space:nowrap}.admin-shell-mark{display:grid;place-items:center;width:30px;height:30px;border-radius:9px;background:linear-gradient(135deg,#6884ff,#78c1d9);color:#091326;font-size:15px}.admin-shell-nav{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.admin-shell-nav a{display:inline-flex;align-items:center;min-height:36px;padding:8px 12px;border:1px solid transparent;border-radius:9px;color:#b6c5df;text-decoration:none;font-size:12px;font-weight:650;white-space:nowrap}.admin-shell-nav a:hover,.admin-shell-nav a:focus-visible{color:#fff;background:#1a2a49;border-color:#4a6091;outline:none}.admin-shell-nav a[aria-current="page"]{color:#fff;background:#263c70;border-color:#6687dc}.admin-shell-label{color:#8093b6;font-size:11px;margin-left:8px;white-space:nowrap}@media(max-width:760px){.admin-shell{display:block;padding:12px 15px}.admin-shell-nav{margin-top:12px;overflow-x:auto;flex-wrap:nowrap;padding-bottom:3px}.admin-shell-nav a{flex:none}.admin-shell-label{display:none}}
</style>
CSS;
}

function adminShellHeader(string $active): string {
    $items=[
        'home'=>['Panel admina','/api/admin-home.php'],
        'briefs'=>['Panel briefów','/api/admin.php'],
        'projects'=>['Centrum projektów','/api/project-portfolio.php'],
        'settings'=>['Ustawienia','/api/project-settings.php'],
    ];
    $html='<header class="admin-shell"><a class="admin-shell-brand" href="/api/admin-home.php"><span class="admin-shell-mark">G</span><span>Grzywniak <span class="admin-shell-label">/ centrum zarządzania</span></span></a><nav class="admin-shell-nav" aria-label="Nawigacja panelu administracyjnego">';
    foreach($items as $id=>[$label,$href]) $html.='<a href="'.$href.'"'.($id===$active?' aria-current="page"':'').'>'.$label.'</a>';
    return $html.'</nav></header>';
}

function adminShellPage(string $html,string $active): string {
    $html=preg_replace('~<header(?:\s[^>]*)?>.*?</header>~s','',$html,1)??$html;
    $html=str_replace('</head>',adminShellStyles().'</head>',$html);
    return str_replace('<body>','<body>'.adminShellHeader($active),$html);
}
