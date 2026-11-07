<?php
declare(strict_types=1);

/**
 * Menu administrace (levý sloupec, na mobilu horní lišta) – společné pro admin/index.php,
 * faktury (admin/invoice.php) a smlouvy (admin/contract.php). $active = klíč aktivní záložky.
 */
function admin_menu(string $active): string
{
    $unread  = (int) q("SELECT COUNT(*) FROM inquiries WHERE is_read = 0")->fetchColumn();
    $pending = (int) q("SELECT COUNT(*) FROM reviews WHERE is_approved = 0")->fetchColumn();
    $tabs = [
        'inquiries' => t('adm.tab.inquiries') . ($unread ? " ($unread)" : ''),
        'customers' => t('adm.tab.customers'),
        'reviews'   => t('adm.tab.reviews') . ($pending ? " ($pending)" : ''),
        'gallery'   => t('adm.tab.gallery'),
        'pages'     => t('adm.tab.pages'),
        'projects'  => t('adm.tab.projects'),
        'form'      => t('adm.tab.form'),
        'texts'     => t('adm.tab.texts'),
        'invoices'  => t('adm.tab.invoices'),
        'settings'  => t('adm.tab.settings'),
    ];
    $h = '<header class="admin-bar"><strong>' . e(site('name')) . '</strong><nav>';
    foreach ($tabs as $k => $label) {
        $href = $k === 'invoices' ? url('admin/invoice.php?list=1') : url('admin/') . '?tab=' . $k;
        $h .= '<a href="' . e($href) . '"' . ($active === $k ? ' class="is-active" aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    return $h . '<a href="' . e(url()) . '" target="_blank">' . te('adm.nav.web') . '</a>'
              . '<a href="' . e(url('admin/') . '?logout=1') . '">' . te('adm.nav.logout') . '</a></nav></header>';
}
