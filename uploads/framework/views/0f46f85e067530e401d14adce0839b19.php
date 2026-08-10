<?php global $s_v_data, $user, $title, $client, $notes, $projects, $staffmembers, $quotes, $invoices, $payments, $jobcards, $statement_invoices, $statement_total, $statement_paid, $statement_balance, $from, $to; ?>
<div class="nk-sidebar nk-sidebar-fixed is-light" data-content="sidebarMenu">
    <div class="nk-sidebar-element nk-sidebar-head">
        <div class="nk-sidebar-brand">
            <a href="<?=  url('Overview@get') ; ?>" class="logo-link nk-sidebar-logo usa-sidebar-brandtext">
                <?php if (!empty($user->parent->logo)) { ?>
                <img src="<?=  env('APP_URL') ; ?>/uploads/logos/<?=  $user->parent->logo ; ?>" alt="<?=  $user->parent->name ; ?>">
                <?php } else { ?>
                <img src="<?=  asset('uploads/logos/unionstar.png') ; ?>" alt="Union Star Auto Garage">
                <?php } ?>
                <span class="usa-sidebar-brandcopy">
                    <strong>UNION STAR</strong>
                    <span>AUTO GARAGE CRM</span>
                </span>
            </a>
        </div>
        <div class="nk-menu-trigger mr-n2">
            <a href="#" class="nk-nav-toggle nk-quick-nav-icon d-xl-none" data-target="sidebarMenu"><em class="icon ni ni-arrow-left"></em></a>
        </div>
    </div>

    <div class="nk-sidebar-element">
        <div class="nk-sidebar-content">
            <div class="nk-sidebar-menu" data-simplebar>
                <ul class="nk-menu">
                    <?php if ($user->role == "Owner" || $user->role == "Manager" || $user->role == "Admin") { ?>
                    <li class="nk-menu-heading"><h6 class="overline-title">Workspace</h6></li>
                    <li class="nk-menu-item">
                        <a href="<?=  env('APP_URL') ; ?>" class="nk-menu-link overview">
                            <span class="nk-menu-icon"><em class="icon ni ni-dashboard-fill"></em></span>
                            <span class="nk-menu-text">Dashboard</span>
                        </a>
                    </li>
                    <li class="nk-menu-item">
                        <a href="<?=  url('Clients@get') ; ?>" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-users-fill"></em></span>
                            <span class="nk-menu-text">Customers</span>
                        </a>
                    </li>
                    <?php } ?>

                    <?php if ($user->role == "Owner" || $user->role == "Booking Manager" || $user->role == "Manager" || $user->role == "Admin") { ?>
                    <li class="nk-menu-heading"><h6 class="overline-title">Workshop</h6></li>
                    <li class="nk-menu-item has-sub">
                        <a href="#" class="nk-menu-link nk-menu-toggle">
                            <span class="nk-menu-icon"><em class="icon ni ni-truck"></em></span>
                            <span class="nk-menu-text">Vehicles & Jobs</span>
                            <?php if ($user->role == "Owner" || $user->role == "Manager" || $user->role == "Admin") { ?>
                            <span class="nk-menu-badge badge-danger"><?=  $user->pendingtasks + $user->expectedparts + $user->unpaidparts ; ?></span>
                            <?php } ?>
                        </a>
                        <ul class="nk-menu-sub">
                            <li class="nk-menu-item"><a href="<?=  url('Projects@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">All Vehicles / Jobs</span></a></li>
                            <?php if ($user->role == "Owner" || $user->role == "Manager" || $user->role == "Admin") { ?>
                            <li class="nk-menu-item"><a href="<?=  url('Tasks@pending') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Pending Tasks</span><span class="nk-menu-badge badge-danger"><?=  $user->pendingtasks ; ?></span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Expenses@expected') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Expected Parts</span><span class="nk-menu-badge badge-danger"><?=  $user->expectedparts ; ?></span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Expenses@unpaid') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Unpaid Parts</span><span class="nk-menu-badge badge-danger"><?=  $user->unpaidparts ; ?></span></a></li>
                            <?php if ($user->parent->insurance == "Enabled") { ?>
                            <li class="nk-menu-item"><a href="<?=  url('Insurance@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Insurance Companies</span></a></li>
                            <?php } ?>
                            <?php } ?>
                        </ul>
                    </li>
                    <?php } ?>

                    <?php if ($user->role == "Owner" || $user->role == "Manager" || $user->role == "Admin") { ?>
                    <li class="nk-menu-heading"><h6 class="overline-title">Finance</h6></li>
                    <li class="nk-menu-item has-sub">
                        <a href="#" class="nk-menu-link nk-menu-toggle">
                            <span class="nk-menu-icon"><em class="icon ni ni-wallet-fill"></em></span>
                            <span class="nk-menu-text">Sales & Payments</span>
                        </a>
                        <ul class="nk-menu-sub">
                            <li class="nk-menu-item"><a href="<?=  url('Quote@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Quotations</span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Invoice@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Invoices</span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Projectpayment@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Payments</span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Clients@statements') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Client Statements</span></a></li>
                        </ul>
                    </li>
                    <?php } ?>

                    <?php if ($user->role == "Owner" || $user->role == "Inventory Manager" || $user->role == "Manager" || $user->role == "Admin") { ?>
                    <li class="nk-menu-item has-sub">
                        <a href="#" class="nk-menu-link nk-menu-toggle">
                            <span class="nk-menu-icon"><em class="icon ni ni-package-fill"></em></span>
                            <span class="nk-menu-text">Inventory</span>
                            <?php if ($user->parent->parts_to_inventory == "Enabled") { ?>
                            <span class="nk-menu-badge badge-danger"><?=  $user->receiveables + $user->issueables ; ?></span>
                            <?php } ?>
                        </a>
                        <ul class="nk-menu-sub">
                            <li class="nk-menu-item"><a href="<?=  url('Inventory@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Stock Items</span></a></li>
                            <?php if ($user->parent->parts_to_inventory == "Enabled") { ?>
                            <li class="nk-menu-item"><a href="<?=  url('Inventory@receiveables') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Receiveables</span><span class="nk-menu-badge badge-danger"><?=  $user->receiveables ; ?></span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Inventory@issueables') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Issueables</span><span class="nk-menu-badge badge-danger"><?=  $user->issueables ; ?></span></a></li>
                            <?php } ?>
                            <li class="nk-menu-item"><a href="<?=  url('Suppliers@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Suppliers</span></a></li>
                        </ul>
                    </li>
                    <?php } ?>

                    <?php if ($user->role == "Owner" || $user->role == "Manager" || $user->role == "Admin") { ?>
                    <li class="nk-menu-item">
                        <a href="<?=  url('Marketing@get') ; ?>" class="nk-menu-link">
                            <span class="nk-menu-icon"><em class="icon ni ni-chat-circle-fill"></em></span>
                            <span class="nk-menu-text">Marketing</span>
                        </a>
                    </li>
                    <?php } ?>

                    <li class="nk-menu-heading"><h6 class="overline-title">Management</h6></li>
                    <?php if ($user->role == "Admin") { ?>
                    <li class="nk-menu-item has-sub">
                        <a href="#" class="nk-menu-link nk-menu-toggle">
                            <span class="nk-menu-icon"><em class="icon ni ni-star-fill"></em></span>
                            <span class="nk-menu-text">Super Admin</span>
                        </a>
                        <ul class="nk-menu-sub">
                            <li class="nk-menu-item"><a href="<?=  url('Plans@overview') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Overview</span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Companies@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Companies</span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Plans@get') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Pricing Plans</span></a></li>
                            <li class="nk-menu-item"><a href="<?=  url('Plans@payments') ; ?>" class="nk-menu-link"><span class="nk-menu-text">Payments</span></a></li>
                        </ul>
                    </li>
                    <?php } ?>
                    <?php if ($user->role == "Owner" || $user->role == "Manager" || $user->role == "Admin") { ?>
                    <li class="nk-menu-item"><a href="<?=  url('Team@get') ; ?>" class="nk-menu-link"><span class="nk-menu-icon"><em class="icon ni ni-user-list-fill"></em></span><span class="nk-menu-text">Team Members</span></a></li>
                    <?php } ?>
                    <li class="nk-menu-item"><a href="<?=  url('Settings@get') ; ?>" class="nk-menu-link"><span class="nk-menu-icon"><em class="icon ni ni-setting-fill"></em></span><span class="nk-menu-text">Settings</span></a></li>
                </ul>

                <div class="usa-sidebar-foot">
                    <strong><?=  $user->parent->name ; ?></strong>
                    <span>Garage operations · <?=  date('Y') ; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php return;
