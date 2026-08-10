<?php global $s_v_data, $user, $title, $clients, $from, $to, $portfolioTotal, $portfolioPaid, $portfolioBalance, $clientsWithBalance; ?>
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

                                <div class="nk-block-head nk-block-head-sm">
                                    <div class="nk-block-between">
                                        <div class="nk-block-head-content">
                                            <h3 class="nk-block-title page-title">Client Statements</h3>
                                            <div class="nk-block-des text-soft">
                                                <p>Generate account statements and review customer balances from one place.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="nk-block">
                                    <div class="card card-bordered usa-statement-hero">
                                        <div class="card-inner">
                                            <form method="GET" action="<?=  url('Clients@statements') ; ?>" id="statement-generator-form">
                                            <div class="row gy-4 align-items-end">
                                                <div class="col-lg-5 col-md-12">
                                                    <div class="form-group">
                                                        <label class="form-label">Customer</label>
                                                            <select class="form-control form-control-lg select2" name="client" required>
                                                                <option value="">Select customer</option>
                                                                <?php foreach ($clients as $client) { ?>
                                                                <option value="<?=  $client->id ; ?>"><?=  $client->fullname ; ?> <?php if (!empty($client->phonenumber)) { ?> — <?=  $client->phonenumber ; ?> <?php } ?></option>
                                                                <?php } ?>
                                                            </select>
                                                    </div>
                                                </div>
                                                <div class="col-lg-2 col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label">From Date</label>
                                                        <input type="date" class="form-control form-control-lg" name="from" value="<?=  $from ; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-2 col-md-4">
                                                    <div class="form-group">
                                                        <label class="form-label">To Date</label>
                                                        <input type="date" class="form-control form-control-lg" name="to" value="<?=  $to ; ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-lg-3 col-md-4">
                                                    <button type="submit" class="btn btn-primary btn-lg btn-block">
                                                        <em class="icon ni ni-file-docs"></em>
                                                        <span>Generate Statement</span>
                                                    </button>
                                                </div>
                                            </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="nk-block">
                                    <div class="row g-gs">
                                        <div class="col-xl-3 col-sm-6">
                                            <div class="card card-bordered h-100">
                                                <div class="card-inner">
                                                    <span class="overline-title text-soft">Total Invoiced</span>
                                                    <div class="d-flex align-items-end justify-content-between mt-1">
                                                        <h4 class="mb-0 text-primary"><?=  money($portfolioTotal, $user->parent->currency) ; ?></h4>
                                                        <em class="icon ni ni-file-text text-primary fs-24px"></em>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-sm-6">
                                            <div class="card card-bordered h-100">
                                                <div class="card-inner">
                                                    <span class="overline-title text-soft">Total Paid</span>
                                                    <div class="d-flex align-items-end justify-content-between mt-1">
                                                        <h4 class="mb-0 text-success"><?=  money($portfolioPaid, $user->parent->currency) ; ?></h4>
                                                        <em class="icon ni ni-check-circle text-success fs-24px"></em>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-sm-6">
                                            <div class="card card-bordered h-100">
                                                <div class="card-inner">
                                                    <span class="overline-title text-soft">Outstanding</span>
                                                    <div class="d-flex align-items-end justify-content-between mt-1">
                                                        <h4 class="mb-0 text-danger"><?=  money($portfolioBalance, $user->parent->currency) ; ?></h4>
                                                        <em class="icon ni ni-alert-circle text-danger fs-24px"></em>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-xl-3 col-sm-6">
                                            <div class="card card-bordered h-100">
                                                <div class="card-inner">
                                                    <span class="overline-title text-soft">Customers With Balance</span>
                                                    <div class="d-flex align-items-end justify-content-between mt-1">
                                                        <h4 class="mb-0"><?=  number_format($clientsWithBalance) ; ?></h4>
                                                        <em class="icon ni ni-users text-info fs-24px"></em>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="nk-block">
                                    <div class="card card-stretch">
                                        <div class="card-inner">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <div>
                                                    <h5 class="title mb-1">Customer Account Overview</h5>
                                                    <p class="text-soft mb-0">Open any customer statement using the selected default period.</p>
                                                </div>
                                            </div>

                                            <table class="datatable-init nk-tb-list nk-tb-ulist" data-auto-responsive="false">
                                                <thead>
                                                    <tr class="nk-tb-item nk-tb-head">
                                                        <th class="nk-tb-col text-center">#</th>
                                                        <th class="nk-tb-col"><span class="sub-text">Customer</span></th>
                                                        <th class="nk-tb-col tb-col-md"><span class="sub-text">Invoices</span></th>
                                                        <th class="nk-tb-col tb-col-md"><span class="sub-text">Invoiced</span></th>
                                                        <th class="nk-tb-col tb-col-md"><span class="sub-text">Paid</span></th>
                                                        <th class="nk-tb-col"><span class="sub-text">Balance</span></th>
                                                        <th class="nk-tb-col nk-tb-col-tools text-right"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($clients)) { ?>
                                                    <?php foreach ($clients as $index => $client) { ?>
                                                    <tr class="nk-tb-item">
                                                        <td class="nk-tb-col text-center"><?=  $index + 1 ; ?></td>
                                                        <td class="nk-tb-col">
                                                            <div class="user-card">
                                                                <div class="user-avatar bg-dim-primary d-none d-sm-flex">
                                                                    <span><?=  mb_substr($client->fullname, 0, 2, 'UTF-8') ; ?></span>
                                                                </div>
                                                                <div class="user-info">
                                                                    <span class="tb-lead"><?=  $client->fullname ; ?></span>
                                                                    <span><?=  $client->phonenumber ; ?></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="nk-tb-col tb-col-md"><span class="tb-amount"><?=  $client->statement_invoices ; ?></span></td>
                                                        <td class="nk-tb-col tb-col-md"><span class="tb-amount"><?=  money($client->statement_total, $user->parent->currency) ; ?></span></td>
                                                        <td class="nk-tb-col tb-col-md"><span class="tb-amount text-success"><?=  money($client->statement_paid, $user->parent->currency) ; ?></span></td>
                                                        <td class="nk-tb-col">
                                                            <?php if ($client->statement_balance > 0.009) { ?>
                                                            <span class="tb-amount text-danger"><?=  money($client->statement_balance, $user->parent->currency) ; ?></span>
                                                            <?php } else { ?>
                                                            <span class="tb-amount text-success"><?=  money($client->statement_balance, $user->parent->currency) ; ?></span>
                                                            <?php } ?>
                                                        </td>
                                                        <td class="nk-tb-col nk-tb-col-tools">
                                                            <ul class="nk-tb-actions gx-1">
                                                                <li>
                                                                    <a href="<?=  url('Clients@details', array('clientid' => $client->id)) ; ?>?view=statement&from=<?=  $from ; ?>&to=<?=  $to ; ?>" class="btn btn-sm btn-dim btn-outline-primary">
                                                                        <em class="icon ni ni-eye"></em><span>Statement</span>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                    <?php } ?>
                                                    <?php } else { ?>
                                                    <tr><td colspan="7" class="text-center">No customers found.</td></tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
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

    <?= view( 'includes/scripts', $s_v_data ); ?>
</body>
</html>

<?php return;
