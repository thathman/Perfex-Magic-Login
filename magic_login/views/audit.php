<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="tw-mb-5">
                    <div>
                        <p class="text-muted tw-mb-1">Security and delivery history</p>
                        <h3 class="tw-font-semibold tw-mt-0">Audit Log</h3>
                        <p class="text-muted tw-max-w-2xl">Review authentication events without exposing token material or loading the full history into the browser.</p>
                    </div>
                </div>

                <ul class="nav nav-tabs nav-tabs-segmented tw-mb-4" role="tablist">
                    <li role="presentation"><a href="<?php echo admin_url('magic_login'); ?>"><i class="fa-solid fa-link tw-mr-1"></i> Login Links</a></li>
                    <li role="presentation" class="active"><a href="<?php echo admin_url('magic_login/audit'); ?>"><i class="fa-regular fa-clock tw-mr-1"></i> Audit Log</a></li>
                </ul>

                <div class="panel_s">
                    <div class="panel-body">
                        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3 tw-mb-4">
                            <div>
                                <h4 class="tw-font-semibold tw-mt-0 tw-mb-1">Authentication activity</h4>
                                <p class="text-muted tw-mb-0">Events are retained by the Magic Login module and paginated at the database layer.</p>
                            </div>
                            <div class="tw-flex tw-flex-wrap tw-items-end tw-gap-2">
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-audit-event">Event</label>
                                    <select id="magic-login-audit-event" name="event" class="form-control" style="width:220px;">
                                        <option value="">All events</option>
                                        <option value="created">Magic link created</option>
                                        <option value="email_request">Email link requested</option>
                                        <option value="used">Magic link used</option>
                                        <option value="revoked">Magic link revoked</option>
                                        <option value="otp_sent">WhatsApp code sent</option>
                                        <option value="otp_verified">WhatsApp code verified</option>
                                        <option value="otp_failed">WhatsApp code failed</option>
                                        <option value="otp_delivery_failed">WhatsApp delivery failed</option>
                                    </select>
                                </div>
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-audit-from">From</label>
                                    <input type="date" id="magic-login-audit-from" name="date_from" class="form-control">
                                </div>
                                <div class="form-group tw-mb-0">
                                    <label class="control-label small" for="magic-login-audit-to">To</label>
                                    <input type="date" id="magic-login-audit-to" name="date_to" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="panel-table-full">
                            <?php render_datatable([
                                ['name' => 'Time', 'th_attrs' => ['class' => 'tw-whitespace-nowrap']], 'Event', 'Contact', 'Token', 'IP address', 'Details',
                            ], 'magic-login-audit', ['responsive-table'], ['data-last-order-identifier' => 'magic-login-audit']); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function () {
    var table = initDataTable('.table-magic-login-audit', admin_url + 'magic_login/audit_table', [], [], {
        event: '#magic-login-audit-event',
        date_from: '#magic-login-audit-from',
        date_to: '#magic-login-audit-to'
    }, [0, 'desc']);
    $('#magic-login-audit-event, #magic-login-audit-from, #magic-login-audit-to').on('change', function () {
        if (table) table.ajax.reload();
    });
})();
</script>
