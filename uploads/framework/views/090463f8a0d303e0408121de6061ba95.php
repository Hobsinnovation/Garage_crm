<?php global $s_v_data, $title, $user, $invoice, $owner; ?>
<?= view( 'includes/head', $s_v_data ); ?>

<script src="<?=  asset('assets/js/jscolor.js') ; ?>"></script>

<body class="nk-body bg-lighter npc-default has-sidebar usa-pdf-viewer-page">
    <div class="nk-app-root">
        <!-- main @s -->
        <div class="nk-main ">
            <!-- sidebar @s -->
            <?= view( 'includes/sidebar', $s_v_data ); ?>
            <!-- sidebar @e -->
            <!-- wrap @s -->
            <div class="nk-wrap ">
                <!-- main header @s -->
                <?= view( 'includes/header', $s_v_data ); ?>
                <!-- main header @e -->
                <!-- content @s -->
                <div class="nk-content ">
                    <div class="container-fluid">
                        <div class="nk-content-inner">
                            <div class="nk-content-body">
                                <div class="nk-block-head nk-block-head-sm">
                                    <div class="nk-block-between">
                                        <div class="nk-block-head-content">
                                            <h3 class="nk-block-title page-title"><?=  $title ; ?></h3>
                                            <div class="nk-block-des text-soft">
                                                <p>You are viewing invoice #<?=  $invoice->id ; ?>.</p>
                                            </div>
                                        </div><!-- .nk-block-head-content -->
                                        <div class="nk-block-head-content">
                                            <ul class="nk-block-tools g-3">
                                                <li>                                                   
                                                 <a href="" class="btn btn-outline-light bg-white d-none d-sm-inline-flex go-back"><em class="icon ni ni-arrow-left"></em><span>Back</span></a>
                                                    <a href="" class="btn btn-icon btn-outline-light bg-white d-inline-flex d-sm-none go-back"><em class="icon ni ni-arrow-left"></em></a>
                                                </li>
                                                <li>
                                                    <div class="drodown">
                                                        <a href="#" class="dropdown-toggle btn btn-dim btn-outline-primary" data-toggle="dropdown"><em class="icon ni ni-more-h"></em> <span>More</span></a>
                                                        <div class="dropdown-menu dropdown-menu-right">
                                                            <ul class="link-list-opt no-bdr">
                                                                <li><a href="<?=  app_url('invoices/'.$invoice->id.'/render') ; ?>" download="Invoice #<?=  $invoice->id ; ?>.pdf"><em class="icon ni ni-download-cloud"></em><span>Download</span></a></li>
                                                                <li><a href="<?=  app_url('invoices/'.$invoice->id.'/render') ; ?>" target="_blank"><em class="icon ni ni-printer"></em><span>Print</span></a></li>
                                                                <li><a href="" class="send-via-email" data-url="<?=  url('Invoice@send') ; ?>" data-id="<?=  $invoice->id ; ?>" data-subject="Invoice #<?=  $invoice->id ; ?>" data-email="<?=  $owner->email ; ?>"><em class="icon ni ni-mail"></em><span>Send Via Email</span></a></li>
                                                                <li><a class="fetch-display-click" data="invoiceid:<?=  $invoice->id ; ?>" url="<?=  url('Invoice@updateview') ; ?>" holder=".update-holder-xl" modal="#update-xl" href=""><em class="icon ni ni-pen"></em><span>Edit Invoice</span></a></li>
                                                                <?php if ($user->parent->invoice_signing == "Enabled" && $invoice->signed == "No") { ?>
                                                                <li><a data-toggle="modal" data-target="#sign"href=""><em class="icon ni ni-edit"></em><span>Client Signature</span></a></li>
                                                                <?php } ?>
                                                                <li class="divider"></li>
                                                                <li><a class="send-to-server-click"  data="invoiceid:<?=  $invoice->id ; ?>" url="<?=  url('Invoice@delete') ; ?>" warning-title="Are you sure?" warning-message="This invoice will be deleted permanently." warning-button="Yes, delete!" href=""><em class="icon ni ni-trash"></em><span>Delete Invoice</span></a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div><!-- .nk-block-between -->
                                </div><!-- .nk-block-head -->
                                <div class="nk-block">
                                    <div class="card card-stretch">
                                        <div class="card-inner">
                                        <!-- start document render -->
                                        <div class="usa-pdf-viewer" data-document="Invoice">
                                            <div class="usa-pdf-toolbar">
                                                <div class="usa-pdf-toolbar-copy">
                                                    <span class="usa-pdf-status-dot"></span>
                                                    <div>
                                                        <strong>Invoice Preview</strong>
                                                        <span>Native browser PDF viewer - reliable on XAMPP and live server</span>
                                                    </div>
                                                </div>
                                                <div class="usa-pdf-toolbar-actions">
                                                    <a class="btn btn-sm btn-outline-primary" href="<?=  app_url('invoices/'.$invoice->id.'/render') ; ?>" target="_blank" rel="noopener">
                                                        <em class="icon ni ni-external"></em><span>Open PDF</span>
                                                    </a>
                                                    <a class="btn btn-sm btn-primary" href="<?=  app_url('invoices/'.$invoice->id.'/render') ; ?>" download>
                                                        <em class="icon ni ni-download-cloud"></em><span>Download</span>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="usa-pdf-frame-wrap">
                                                <iframe
                                                    class="usa-pdf-frame"
                                                    src="<?=  app_url('invoices/'.$invoice->id.'/render') ; ?>?viewer=native&v=<?=  date('YmdHis') ; ?>"
                                                    title="Invoice PDF Preview"
                                                    loading="eager">
                                                </iframe>
                                            </div>
                                            <noscript>
                                                <div class="usa-pdf-fallback">JavaScript is disabled. <a href="<?=  app_url('invoices/'.$invoice->id.'/render') ; ?>" target="_blank">Open the PDF directly.</a></div>
                                            </noscript>
                                        </div>
                                        <!-- end document render -->
                                        </div>
                                    </div><!-- .card -->
                                </div><!-- .nk-block -->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- content @e -->
                <!-- footer @s -->
                <?= view( 'includes/footer', $s_v_data ); ?>
                <!-- footer @e -->
            </div>
            <!-- wrap @e -->
        </div>
        <!-- main @e -->
    </div>


    <?php if ($user->parent->invoice_signing == "Enabled" && $invoice->signed == "No") { ?>
    <!-- Modal add expense -->
    <div class="modal fade" tabindex="-1" id="sign">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <a href="#" class="close" data-dismiss="modal" aria-label="Close">
                    <em class="icon ni ni-cross"></em>
                </a>
                <div class="modal-header">
                    <h5 class="modal-title">Client Signature</h5>
                </div>
                <form class="simcy-form signing-form" action="<?=  url('Invoice@sign') ; ?>" data-parsley-validate="" method="POST" loader="true">
                    <div class="modal-body">
                        <p>Once the client has signed, any changes made on this invoice will erase the client signature and will require a new signature.</p>
                        <div class="row gy-4">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <div class="draw-signature-holder"><canvas width="320" height="150" id="draw-signature"></canvas></div>
                                    <input type="hidden" name="signature">
                                    <input type="hidden" name="invoiceid" value="<?=  $invoice->id ; ?>" required="">
                                    <div class="signature-tools text-center" id="controls">
                                        <div class="signature-tool-item with-picker">
                                            <div><button class="jscolor { valueElement:null,borderRadius:'1px', borderColor:'#e6eaee',value:'1418FF',zIndex:'99999', onFineChange:'modules.color(this)'}"></button></div>
                                        </div>
                                        <div class="signature-tool-item" id="signature-stroke" stroke="5">
                                            <div class="tool-icon tool-stroke"></div>
                                        </div>
                                        <div class="signature-tool-item" id="undo">
                                            <div class="tool-icon tool-undo"></div>
                                        </div>
                                        <div class="signature-tool-item" id="clear">
                                            <div class="tool-icon tool-erase"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                        <div class="row gy-4">
                            <div class="col-sm-12">
                                <div class="nk-divider divider mt-0 mb-0"></div>
                            </div>
                            <div class="col-sm-12">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <div class="form-control-wrap">
                                        <input type="text" class="form-control form-control-lg" placeholder="Full Name" name="fullname" required="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button class="btn btn-white btn-dim btn-outline-light" type="button" data-dismiss="modal"><em class="icon ni ni-cross-circle"></em><span>Cancel</span></button>
                        <button class="btn btn-primary sign-document" type="button"><em class="icon ni ni-check-circle-cut"></em><span>Sign Invoice</span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- app-root @e -->
    <!-- JavaScript -->
    <?= view( 'includes/scripts', $s_v_data ); ?>
<script src="<?=  asset('assets/libs/jcanvas/signature.min.js') ; ?>"></script>
<script src="<?=  asset('assets/js/sign-documents.js') ; ?>"></script>
</body>

</html>
<?php return;
