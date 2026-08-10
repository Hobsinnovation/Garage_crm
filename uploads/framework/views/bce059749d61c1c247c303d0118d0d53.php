<?php global $s_v_data, $user, $title, $widgets, $projects, $tasks, $income, $recent; ?>
<?= view( 'includes/head', $s_v_data ); ?>

<body class="nk-body bg-lighter npc-default has-sidebar">
<div class="nk-app-root">
    <div class="nk-main">
        <?= view( 'includes/sidebar', $s_v_data ); ?>
        <div class="nk-wrap">
            <?= view( 'includes/header', $s_v_data ); ?>

            <div class="nk-content">
                <div class="container-fluid">
                    <div class="nk-content-inner">
                        <div class="nk-content-body">

                            <div class="usa-dashboard-head">
                                <div>
                                    <h1>Garage Command Center</h1>
                                    <p>Live overview of workshop activity, revenue, receivables and stock.</p>
                                </div>
                                <div class="usa-dashboard-actions">
                                    <a href="<?=  url('Clients@get') ; ?>" class="btn btn-sm btn-outline-primary"><em class="icon ni ni-users"></em><span>Customers</span></a>
                                    <a href="<?=  url('Projects@get') ; ?>" class="btn btn-sm btn-primary"><em class="icon ni ni-truck"></em><span>Vehicles & Jobs</span></a>
                                </div>
                            </div>

                            <?php if ((strtotime($user->parent->subscription_end) - time()) < 345600 && $user->parent->admin == "No") { ?>
                            <div class="mb-3">
                                <a href="<?=  url('Billing@get') ; ?>">
                                    <div class="alert alert-warning alert-icon"><em class="icon ni ni-alert-circle"></em><strong>Subscription notice:</strong> expires in <?=  timeLeft(strtotime($user->parent->subscription_end)) ; ?>.</div>
                                </a>
                            </div>
                            <?php } ?>

                            <div class="usa-kpi-grid">
                                <div class="usa-kpi">
                                    <div class="usa-kpi-top"><div><div class="usa-kpi-label">Today's Revenue</div><div class="usa-kpi-value"><?=  money($widgets["todayrevenue"], $user->parent->currency) ; ?></div></div><div class="usa-kpi-icon blue"><em class="icon ni ni-coins"></em></div></div>
                                    <div class="usa-kpi-note"><strong><?=  $widgets["paymentsthismonth"] ; ?></strong> payments recorded this month</div>
                                </div>
                                <div class="usa-kpi">
                                    <div class="usa-kpi-top"><div><div class="usa-kpi-label">Monthly Revenue</div><div class="usa-kpi-value"><?=  money($widgets["incomethismonth"], $user->parent->currency) ; ?></div></div><div class="usa-kpi-icon green"><em class="icon ni ni-growth-fill"></em></div></div>
                                    <div class="usa-kpi-note">Current month collections</div>
                                </div>
                                <div class="usa-kpi">
                                    <div class="usa-kpi-top"><div><div class="usa-kpi-label">Cars In Workshop</div><div class="usa-kpi-value"><?=  number_format($widgets["activeprojects"]) ; ?></div></div><div class="usa-kpi-icon orange"><em class="icon ni ni-truck"></em></div></div>
                                    <div class="usa-kpi-note"><strong><?=  $widgets["bookedinprojects"] ; ?></strong> booked in · <strong><?=  $widgets["completedprojects"] ; ?></strong> completed</div>
                                </div>
                                <div class="usa-kpi">
                                    <div class="usa-kpi-top"><div><div class="usa-kpi-label">Pending Tasks</div><div class="usa-kpi-value"><?=  number_format($widgets["pendingtasks"]) ; ?></div></div><div class="usa-kpi-icon purple"><em class="icon ni ni-todo-fill"></em></div></div>
                                    <div class="usa-kpi-note"><strong><?=  $widgets["completedtasks"] ; ?></strong> tasks completed</div>
                                </div>
                                <div class="usa-kpi">
                                    <div class="usa-kpi-top"><div><div class="usa-kpi-label">Unpaid Invoices</div><div class="usa-kpi-value"><?=  money($widgets["unpaidinvoices"], $user->parent->currency) ; ?></div></div><div class="usa-kpi-icon red"><em class="icon ni ni-file-docs"></em></div></div>
                                    <div class="usa-kpi-note"><strong><?=  money($widgets["overdueinvoices"], $user->parent->currency) ; ?></strong> overdue</div>
                                </div>
                                <div class="usa-kpi">
                                    <div class="usa-kpi-top"><div><div class="usa-kpi-label">Low Stock Items</div><div class="usa-kpi-value"><?=  number_format($widgets["lowstock"]) ; ?></div></div><div class="usa-kpi-icon cyan"><em class="icon ni ni-package-fill"></em></div></div>
                                    <div class="usa-kpi-note"><strong><?=  $widgets["inventoryitems"] ; ?></strong> total inventory items</div>
                                </div>
                            </div>

                            <div class="usa-dashboard-grid">
                                <div class="usa-panel">
                                    <div class="usa-panel-head"><h3>Workshop Overview</h3><a href="<?=  url('Projects@get') ; ?>">View all vehicles</a></div>
                                    <div class="usa-workflow">
                                        <div class="usa-stage">
                                            <div class="usa-stage-title"><span>Booked In</span><span class="usa-stage-count"><?=  $projects["bookedin"] ; ?></span></div>
                                            <?php foreach ($recent['projects'] as $project) { ?>
                                            <?php if ($project->status == "Booked In") { ?>
                                            <a href="<?=  url('Projects@details', array('projectid' => $project->id)) ; ?>" class="usa-job"><strong><?=  carmake($project->make) ; ?> <?=  carmodel($project->model) ; ?></strong><span><?=  $project->registration_number ; ?> · <?=  $project->client_name ; ?></span><span class="usa-status booked-in">Booked In</span></a>
                                            <?php } ?>
                                            <?php } ?>
                                        </div>
                                        <div class="usa-stage">
                                            <div class="usa-stage-title"><span>In Progress</span><span class="usa-stage-count"><?=  $projects["progress"] ; ?></span></div>
                                            <?php foreach ($recent['projects'] as $project) { ?>
                                            <?php if ($project->status == "In Progress") { ?>
                                            <a href="<?=  url('Projects@details', array('projectid' => $project->id)) ; ?>" class="usa-job"><strong><?=  carmake($project->make) ; ?> <?=  carmodel($project->model) ; ?></strong><span><?=  $project->registration_number ; ?> · <?=  $project->client_name ; ?></span><span class="usa-status in-progress">In Progress</span></a>
                                            <?php } ?>
                                            <?php } ?>
                                        </div>
                                        <div class="usa-stage">
                                            <div class="usa-stage-title"><span>Completed</span><span class="usa-stage-count"><?=  $projects["complete"] ; ?></span></div>
                                            <?php foreach ($recent['projects'] as $project) { ?>
                                            <?php if ($project->status == "Completed") { ?>
                                            <a href="<?=  url('Projects@details', array('projectid' => $project->id)) ; ?>" class="usa-job"><strong><?=  carmake($project->make) ; ?> <?=  carmodel($project->model) ; ?></strong><span><?=  $project->registration_number ; ?> · <?=  $project->client_name ; ?></span><span class="usa-status completed">Completed</span></a>
                                            <?php } ?>
                                            <?php } ?>
                                        </div>
                                        <div class="usa-stage">
                                            <div class="usa-stage-title"><span>Attention</span><span class="usa-stage-count"><?=  $projects["cancelled"] + $widgets["pendingtasks"] ; ?></span></div>
                                            <a href="<?=  url('Tasks@pending') ; ?>" class="usa-job"><strong>Pending workshop tasks</strong><span>Tasks waiting for completion</span><span class="usa-status in-progress"><?=  $widgets["pendingtasks"] ; ?> Pending</span></a>
                                            <?php if ($widgets["lowstock"] > 0) { ?>
                                            <a href="<?=  url('Inventory@get') ; ?>" class="usa-job"><strong>Inventory needs attention</strong><span>Items at or below restock level</span><span class="usa-status unpaid"><?=  $widgets["lowstock"] ; ?> Low Stock</span></a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="usa-panel">
                                    <div class="usa-panel-head"><h3>Revenue Overview</h3><span>Last 12 months</span></div>
                                    <div class="usa-panel-body"><div class="usa-chart-wrap"><canvas class="line-chart" id="userGrowth"></canvas></div></div>
                                    <div class="usa-profit-strip">
                                        <div class="usa-profit-cell"><span>Invoiced <?=  date('Y') ; ?></span><strong><?=  money($widgets["invoicedyear"], $user->parent->currency) ; ?></strong></div>
                                        <div class="usa-profit-cell"><span>Costs <?=  date('Y') ; ?></span><strong><?=  money($widgets["expensesyear"], $user->parent->currency) ; ?></strong></div>
                                        <div class="usa-profit-cell"><span>Estimated Profit</span><strong><?=  money($widgets["profits"], $user->parent->currency) ; ?></strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="usa-bottom-grid">
                                <div class="usa-panel">
                                    <div class="usa-panel-head"><h3>Recent Invoices</h3><a href="<?=  url('Invoice@get') ; ?>">View all invoices</a></div>
                                    <div class="usa-panel-body">
                                        <?php if (!empty($recent['invoices'])) { ?>
                                        <ul class="usa-list">
                                            <?php foreach ($recent['invoices'] as $invoice) { ?>
                                            <li class="usa-list-item">
                                                <div class="usa-list-main"><strong><a href="<?=  url('Invoice@view', array('invoiceid' => $invoice->id)) ; ?>">INV-<?=  str_pad($invoice->id, 5, '0', STR_PAD_LEFT) ; ?></a> · <?=  $invoice->client_name ; ?></strong><span><?=  $invoice->registration_number ; ?> · <?=  !empty($invoice->invoice_date) ? date('d M Y', strtotime($invoice->invoice_date)) : '' ; ?></span></div>
                                                <div class="usa-list-value"><strong><?=  money($invoice->total, $user->parent->currency) ; ?></strong><span class="usa-status <?=  strtolower($invoice->status) ; ?>"><?=  $invoice->status ; ?></span></div>
                                            </li>
                                            <?php } ?>
                                        </ul>
                                        <?php } else { ?>
                                        <div class="usa-empty">No invoices found.</div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="usa-panel">
                                    <div class="usa-panel-head"><h3>Low Stock Watch</h3><a href="<?=  url('Inventory@get') ; ?>">Open inventory</a></div>
                                    <div class="usa-panel-body">
                                        <?php if (!empty($recent['stock'])) { ?>
                                        <ul class="usa-list">
                                            <?php foreach ($recent['stock'] as $stock) { ?>
                                            <li class="usa-list-item">
                                                <div class="usa-list-main"><strong><?=  $stock->name ; ?></strong><span><?=  !empty($stock->item_code) ? $stock->item_code : 'No item code' ; ?></span></div>
                                                <div class="usa-list-value"><strong><?=  $stock->quantity ; ?> <?=  $stock->quantity_unit ; ?></strong><span class="text-danger">Restock at <?=  $stock->restock_quantity ; ?></span></div>
                                            </li>
                                            <?php } ?>
                                        </ul>
                                        <?php } else { ?>
                                        <div class="usa-empty">Stock levels look healthy.</div>
                                        <?php } ?>
                                    </div>
                                </div>

                                <div class="usa-panel">
                                    <div class="usa-panel-head"><h3>Business Snapshot</h3><span><?=  date('M Y') ; ?></span></div>
                                    <div class="usa-panel-body">
                                        <ul class="usa-list">
                                            <li class="usa-list-item"><div class="usa-list-main"><strong>Total Customers</strong><span>Customer database</span></div><div class="usa-list-value"><strong><?=  number_format($widgets["totalclients"]) ; ?></strong></div></li>
                                            <li class="usa-list-item"><div class="usa-list-main"><strong>Team Members</strong><span>Active & inactive staff</span></div><div class="usa-list-value"><strong><?=  number_format($widgets["totalstaff"]) ; ?></strong></div></li>
                                            <li class="usa-list-item"><div class="usa-list-main"><strong>Revenue <?=  date('Y') ; ?></strong><span>Payments received</span></div><div class="usa-list-value"><strong><?=  money($widgets["incomethisyear"], $user->parent->currency) ; ?></strong></div></li>
                                            <li class="usa-list-item"><div class="usa-list-main"><strong>Outstanding</strong><span>Open invoice balance</span></div><div class="usa-list-value"><strong><?=  money($widgets["unpaidinvoices"], $user->parent->currency) ; ?></strong></div></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="usa-panel">
                                <div class="usa-quickbar">
                                    <a href="<?=  url('Clients@get') ; ?>"><em class="icon ni ni-user-add"></em>Customers</a>
                                    <a href="<?=  url('Projects@get') ; ?>"><em class="icon ni ni-truck"></em>Vehicles</a>
                                    <a href="<?=  url('Quote@get') ; ?>"><em class="icon ni ni-file-docs"></em>Quotations</a>
                                    <a href="<?=  url('Invoice@get') ; ?>"><em class="icon ni ni-reports"></em>Invoices</a>
                                    <a href="<?=  url('Projectpayment@get') ; ?>"><em class="icon ni ni-wallet"></em>Payments</a>
                                    <a href="<?=  url('Inventory@get') ; ?>"><em class="icon ni ni-package"></em>Inventory</a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <?= view( 'includes/footer', $s_v_data ); ?>
        </div>
    </div>
</div>

<script type="text/javascript">
    var currency = "<?=  $user->parent->currency ; ?>";
    var projectData = [<?=  $projects["complete"] ; ?>, <?=  $projects["progress"] ; ?>, <?=  $projects["bookedin"] ; ?>, <?=  $projects["cancelled"] ; ?>];
    var tasksData = [<?=  $tasks["complete"] ; ?>, <?=  $tasks["progress"] ; ?>, <?=  $tasks["cancelled"] ; ?>];
    var amount = ["<?=  implode('\", \"', $income['amount']) ; ?>"];
    var labels = ["<?=  implode('\", \"', $income['label']) ; ?>"];
</script>

<?= view( 'includes/scripts', $s_v_data ); ?>
<script src="<?=  asset('assets/js/charts/statistics.js') ; ?>"></script>
</body>
</html>

<?php return;
