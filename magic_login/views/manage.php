<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-flex tw-items-start tw-justify-between tw-gap-4 tw-mb-5">
                    <div>
                        <p class="text-muted tw-mb-1">Passwordless access operations</p>
                        <h3 class="tw-font-semibold tw-mt-0">Magic Login</h3>
                        <p class="text-muted tw-max-w-2xl">Create, monitor and revoke secure client links without loading the configuration surface into your daily workflow.</p>
                    </div>
                    <?php if (staff_can('create', 'magic_login') || is_admin()) { ?>
                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#magic-login-create-modal">
                            <i class="fa-regular fa-plus tw-mr-1"></i> Create Magic Login
                        </button>
                    <?php } ?>
                </div>

                <ul class="nav nav-tabs nav-tabs-segmented tw-mb-4" role="tablist">
                    <li role="presentation" class="active"><a href="<?php echo admin_url('magic_login'); ?>"><i class="fa-solid fa-link tw-mr-1"></i> Login Links</a></li>
                    <li role="presentation"><a href="<?php echo admin_url('magic_login/audit'); ?>"><i class="fa-regular fa-clock tw-mr-1"></i> Audit Log</a></li>
                </ul>

                <div class="row">
                    <?php
                    $statCards = [
                        ['label' => 'Active links', 'value' => (int) ($stats['active'] ?? 0), 'icon' => 'fa-solid fa-bolt', 'class' => 'text-info'],
                        ['label' => 'Used today', 'value' => (int) ($stats['used_today'] ?? 0), 'icon' => 'fa-solid fa-check', 'class' => 'text-success'],
                        ['label' => 'Expired', 'value' => (int) ($stats['expired'] ?? 0), 'icon' => 'fa-regular fa-hourglass', 'class' => 'text-muted'],
                        ['label' => 'Failed OTP attempts today', 'value' => (int) ($stats['failed_otp_today'] ?? 0), 'icon' => 'fa-solid fa-shield-halved', 'class' => 'text-warning', 'title' => 'Unsuccessful one-time-code verification attempts since midnight.'],
                    ];
                    foreach ($statCards as $card) { ?>
                        <div class="col-md-3 col-sm-6">
                            <div class="panel_s">
                                <div class="panel-body tw-flex tw-items-center tw-gap-3">
                                    <div class="tw-flex tw-h-10 tw-w-10 tw-items-center tw-justify-center tw-rounded-lg tw-bg-neutral-100 <?php echo $card['class']; ?>">
                                        <i class="<?php echo $card['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="text-muted small"<?php echo isset($card['title']) ? ' title="' . html_escape($card['title']) . '"' : ''; ?>><?php echo $card['label']; ?></div>
                                        <div class="tw-text-2xl tw-font-semibold"><?php echo $card['value']; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>

                <?php $link = $this->session->flashdata('magic_login_link'); if ($link) { ?>
                    <div class="alert alert-success tw-flex tw-items-start tw-justify-between tw-gap-3">
                        <div><strong>Link created.</strong><br><code class="tw-break-all"><?php echo html_escape($link); ?></code></div>
                        <button type="button" class="btn btn-default btn-sm" data-copy-value="<?php echo html_escape($link); ?>"><i class="fa-regular fa-copy"></i> Copy</button>
                    </div>
                <?php } ?>

                <div class="panel_s" style="overflow:hidden;">
                    <div class="panel-body">
                        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3 tw-mb-4">
                            <div>
                                <h4 class="tw-font-semibold tw-mt-0 tw-mb-1">Login links</h4>
                                <p class="text-muted tw-mb-0">Search and manage links as the table grows. Tokens are never displayed.</p>
                            </div>
                            <div class="tw-flex tw-flex-wrap tw-items-end tw-gap-2">
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-status">Status</label>
                                    <select id="magic-login-status" name="status" class="form-control" style="width:150px;">
                                        <option value="">All statuses</option>
                                        <option value="active">Active</option>
                                        <option value="used">Used</option>
                                        <option value="expired">Expired</option>
                                        <option value="revoked">Revoked</option>
                                    </select>
                                </div>
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-source">Source</label>
                                    <select id="magic-login-source" name="source" class="form-control" style="width:150px;">
                                        <option value="">All sources</option>
                                        <option value="manual">Manual</option>
                                        <option value="email">Email</option>
                                        <option value="api">API</option>
                                        <option value="whatsapp">WhatsApp</option>
                                    </select>
                                </div>
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-from">Created from</label>
                                    <input type="date" id="magic-login-from" name="date_from" class="form-control">
                                </div>
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-to">Created to</label>
                                    <input type="date" id="magic-login-to" name="date_to" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="panel-table-full">
                            <?php render_datatable([
                                'Contact', 'Source', 'Context', 'Destination', 'Created', 'Expires', 'Status', ['name' => 'Options', 'th_attrs' => ['class' => 'not-export']],
                            ], 'magic-login', ['responsive-table'], ['data-last-order-identifier' => 'magic-login']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (staff_can('create', 'magic_login') || is_admin()) { ?>
<div class="modal fade" id="magic-login-create-modal" tabindex="-1" role="dialog" aria-labelledby="magic-login-create-title">
    <div class="modal-dialog modal-lg" role="document">
        <?php echo form_open(admin_url('magic_login/create'), ['id' => 'magic-login-create-form']); ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="magic-login-create-title">Create Magic Login</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">Choose a client contact and where the link should open after authentication.</p>
                <div class="form-group">
                    <label for="magic_login_contact_id">Contact</label>
                    <select class="ajax-search" name="contact_id" id="magic_login_contact_id" required data-width="100%" data-live-search="true" data-none-selected-text="Search contacts">
                        <option value=""></option>
                    </select>
                    <p class="text-muted mtop5">Search by name, email or company.</p>
                </div>
                <?php $defaultHours = max(1, (int) ceil((int) get_option('magic_login_default_expiry_minutes') / 60)); ?>
                <?php echo render_input('hours', 'Validity (hours)', (string) $defaultHours, 'number', ['min' => 1, 'max' => 168]); ?>
                <div class="form-group">
                    <label for="portal_endpoint">Destination</label>
                    <select class="form-control" name="portal_endpoint" id="portal_endpoint">
                        <?php foreach (($endpoint_options ?? []) as $endpointValue => $endpointLabel) { ?>
                            <option value="<?php echo html_escape($endpointValue); ?>" <?php echo $endpointValue === 'clients' ? 'selected' : ''; ?>><?php echo html_escape($endpointLabel); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group hide" id="custom-endpoint-wrap">
                    <label for="custom_endpoint">Custom endpoint</label>
                    <input type="text" class="form-control" name="custom_endpoint" id="custom_endpoint" placeholder="clients/invoices">
                    <p class="text-muted mtop5">Use a same-site relative path only.</p>
                </div>
                <div class="checkbox checkbox-primary">
                    <input type="checkbox" id="send_email" name="send_email" value="1" checked>
                    <label for="send_email">Send the link to the contact by email</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-link tw-mr-1"></i> Create link</button>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</div>
<?php } ?>

<?php init_tail(); ?>
<script>
(function () {
    var table;
    var endpoint = document.getElementById('portal_endpoint');
    var customWrap = document.getElementById('custom-endpoint-wrap');
    var contact = $('#magic_login_contact_id');

    if (contact.length && typeof init_ajax_search === 'function') {
        init_ajax_search('contact', '#magic_login_contact_id.ajax-search', undefined, admin_url + 'magic_login/contacts');
    }

    function syncCustom() {
        if (!endpoint || !customWrap) return;
        customWrap.classList.toggle('hide', endpoint.value !== 'custom');
    }
    if (endpoint) {
        endpoint.addEventListener('change', syncCustom);
        syncCustom();
    }

    table = initDataTable('.table-magic-login', admin_url + 'magic_login/table', [7], [7], {
        status: '#magic-login-status',
        source: '#magic-login-source',
        date_from: '#magic-login-from',
        date_to: '#magic-login-to'
    }, [4, 'desc']);
    $('#magic-login-status, #magic-login-source, #magic-login-from, #magic-login-to').on('change', function () {
        if (table) table.ajax.reload();
    });

    $('[data-copy-value]').on('click', function () {
        var value = $(this).attr('data-copy-value');
        navigator.clipboard && navigator.clipboard.writeText(value).then(function () {
            alert_float('success', 'Link copied to clipboard.');
        });
    });
})();
</script>
