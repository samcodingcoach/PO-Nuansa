<?php
/**
 * PRINT PURCHASE ORDER - FINAL STABLE VERSION
 * Fix: Barcode Print Visibility, Expedition Mapping, & Clean Layout
 */
require_once __DIR__ . '/../bootstrap.php';

$api = new AccurateAPI();
$nomor_po = isset($_GET['nomor_po']) ? $_GET['nomor_po'] : '';

$dataPO = array();
$dataVendor = array();
$dataBranch = array();

if ($nomor_po) {
    $poRes = $api->getPurchaseOrderDetail($nomor_po);
    if ($poRes['success'] && isset($poRes['data']['d'])) {
        $dataPO = $poRes['data']['d'];
        
        // 1. Ambil branchId (Flat)
        $bId = isset($dataPO['branchId']) ? $dataPO['branchId'] : null;
        if ($bId) {
            $bRes = $api->getBranchDetail($bId);
            if (isset($bRes['data']['d'])) {
                $dataBranch = $bRes['data']['d'];
            }
        }

        // 2. Get Detail Vendor
        $vNo = isset($dataPO['vendor']['vendorNo']) ? $dataPO['vendor']['vendorNo'] : null;
        if ($vNo) {
            $vRes = $api->getVendorDetail(null, $vNo);
            if (isset($vRes['data']['d'])) {
                $dataVendor = $vRes['data']['d'];
            }
        }
    }
}

// --- FUNGSI BARCODE 128 (FIXED PRINT VISIBILITY) ---
global $char128asc, $char128wid;
$char128asc=' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';					
$char128wid = array('212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','23311120');

function bar128($text) {
    global $char128asc, $char128wid;
    $text = trim($text);
    if (empty($text)) return '';
    $sum = 104; $w = $char128wid[104]; $onChar = 1;
    for($x=0;$x<strlen($text);$x++) {
        if (!( ($pos = strpos($char128asc,$text[$x])) === false )){
            $w.= $char128wid[$pos];
            $sum += $onChar++ * $pos;
        }
    }                   
    $w.= $char128wid[ $sum % 103 ].$char128wid[106];
    
    // Fix Print: Tambahkan -webkit-print-color-adjust
    $printFix = "-webkit-print-color-adjust:exact; print-color-adjust:exact;";
    $html="<div style='display:inline-block; white-space:nowrap; $printFix'>";              
    for($x=0;$x<strlen($w);$x+=2) {
        $black = $w[$x];
        $white = $w[$x+1];
        $html .= "<div style='display:inline-block; background:black !important; height:30px; width:{$black}px; $printFix'></div>";
        $html .= "<div style='display:inline-block; background:transparent; height:30px; width:{$white}px;'></div>";
    }
    return $html . "</div>";     
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>PURCHASE ORDER - <?php echo isset($dataPO['number']) ? $dataPO['number'] : ''; ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 20px; -webkit-print-color-adjust: exact; }
        table { border-collapse: collapse; width: 100%; }
        .table-item td, .table-item th { border: 0; padding: 6px 4px; }
        .line-bottom { border-bottom: 1px solid black; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td rowspan="4" width="220"><img src="images/Nuansa.jpg" width="200" height="75" /></td>
            <td rowspan="2" style="font-weight:bold; font-size:18px;">PURCHASE ORDER</td>
            <td width="100">Tanggal PO :</td>
            <td width="100">Tanggal Kirim :</td>
            <td width="100">Tanggal Exp :</td>
        </tr>
        <tr>
            <td valign="top"><?php echo isset($dataPO['transDateView']) ? $dataPO['transDateView'] : '-'; ?></td>
            <td valign="top"><?php echo isset($dataPO['shipDateView']) ? $dataPO['shipDateView'] : '-'; ?></td>
            <td>
                <?php 
                if (isset($dataPO['transDate']) && isset($dataPO['autoCloseRange'])) {
                    $poDate = str_replace('/', '-', $dataPO['transDate']);
                    $days = intval($dataPO['autoCloseRange']);
                    
                    // Format: Tgl Bulan Tahun (Jumlah Hari)
                    echo date('d M Y', strtotime($poDate . " + " . $days . " days"));
                    echo "<br>(" . $days . " Hari)";
                }
                ?>
            </td>
        </tr>
        <tr>
            <td rowspan="2">RE-PRINT</td>
            <td rowspan="2" valign="middle"><?php if(!empty($dataPO['number'])) echo bar128($dataPO['number']); ?></td>
            <td>NO. PO :</td>
            <td>T.O.P :</td>
        </tr>
        <tr>
            <td><strong><?php echo isset($dataPO['number']) ? $dataPO['number'] : '-'; ?></strong></td>
            <td><?php echo isset($dataPO['paymentTerm']['name']) ? $dataPO['paymentTerm']['name'] : '-'; ?></td>
        </tr>
    </table>

    <table style="border: 1px solid black; margin-top:10px;">
        <tr>
            <td width="50%" valign="top" style="border-right: 2px solid black; padding:8px;">
                <table width="100%">
                    <tr><td width="30%">SUPPLIER:</td><td><strong><?php echo isset($dataPO['vendor']['name']) ? $dataPO['vendor']['name'] : '-'; ?></strong></td></tr>
                    <tr>
                        <td></td>
                        <td>
                            <?php 
                            $output = trim((isset($dataVendor['billCity']) ? $dataVendor['billCity'] : '') . 
                                    ($dataVendor['billCity'] && $dataVendor['billProvince'] ? ' - ' : '') . 
                                    (isset($dataVendor['billProvince']) ? $dataVendor['billProvince'] : ''));
                            
                            echo ($output !== '') ? $output : '-';
                            ?>
                        </td>
                    </tr>
                    <tr><td>TELEPON:</td><td><?php echo isset($dataVendor['mobilePhone']) ? $dataVendor['mobilePhone'] : '-'; ?></td></tr>
                    <tr><td>CONTACT:</td><td><?php echo isset($dataVendor['detailContact'][0]['name']) ? $dataVendor['detailContact'][0]['name'] : '-'; ?></td></tr>
                </table>
            </td>
            <td width="50%" valign="top" style="padding:8px;">
                <table width="100%">
                    <tr>
                        <td width="25%">TOKO :</td>
                        <td>
                            <strong>
                                <?php 
                                $street = isset($dataBranch['street']) ? trim($dataBranch['street']) : '';
                                $name   = isset($dataBranch['name']) ? trim($dataBranch['name']) : '';

                                if ($street !== '' && $name !== '') {
                                    echo $street . ' - ' . $name;
                                } elseif ($street !== '' || $name !== '') {
                                    echo $street . $name;
                                } else {
                                    echo '-';
                                }
                                ?>
                            </strong>
                        </td>
                    </tr>
                    <tr><td>SHIP TO :</td><td><br><?php echo isset($dataPO['toAddress']) ? $dataPO['toAddress'] : '-'; ?></td></tr>
                    <?php if (isset($dataPO['shipment']['name'])) : ?>
                    <tr>
                        <td><br>EKSPEDISI :</td>
                        <td>
                            <br>
                            <strong><?php echo isset($dataPO['shipment']['name']) ? $dataPO['shipment']['name'] : '-'; ?></strong>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </td>
        </tr>
    </table>
    <!-- DETAIL BARANG PO -->
    <table class="table-item" style="margin-top:10px;">
        <tr style= font-weight:bold; text-align:center;">
            <th width="5%" class="line-bottom">NO.</th>
            <th width="45%" class="line-bottom" align="left">NAMA BARANG</th>
            <th width="15%" class="line-bottom">SITE</th>
            <th width="10%" class="line-bottom">QTY</th>
            <th width="25%" class="line-bottom">BARCODE</th>
        </tr>
        <?php 
        $totalQty = 0;
        if (isset($dataPO['detailItem'])) {
            foreach ($dataPO['detailItem'] as $idx => $item) {
                $totalQty += $item['quantity'];
                ?>
                <tr>
                    <td align="center"><?php echo $idx + 1; ?></td>
                    <td><?php echo $item['detailName']; ?></td>
                    <td align="center"><?php echo $item['warehouse']['name']; ?></td>
                    <td align="center"><?php echo number_format($item['quantity'], 0); ?></td>
                    <td align="center" style="padding: 10px 0;">
                        <?php 
                        $upc = isset($item['item']['upcNo']) ? trim($item['item']['upcNo']) : '';
                        if ($upc != '') { echo bar128($upc); } else { echo "-"; }
                        ?>
                    </td>
                </tr>
                <?php
            }
        }
        ?>
        <tr>
            <td colspan="3" class="line-bottom"></td>
            <td align="center" style="font-weight:bold;" class="line-bottom"><?php echo "TOTAL QTY: " . number_format($totalQty, 0); ?></td>
            <td class="line-bottom"></td>
        </tr>
    </table>
    <!-- DETAIL BARANG PO -->
    
    <!-- FOOTER GRUP  -->
    <table style="margin-top:15px;">
        <tr><td colspan="4">KET: <?php echo isset($dataPO['description']) ? $dataPO['description'] : '-'; ?></td></tr>
        <tr style="text-align:center;">
            <td width="25%" style="padding-top:20px;">Mengetahui</td>
            <td width="25%">&nbsp;</td>
            <td width="25%" style="padding-top:20px;">DATETIME: <?php echo date("d M Y H:i"); ?></td>
            <td width="25%" style="padding-top:20px;">
                USER: 
                <?php 
                    $createdBy = isset($dataPO['createdBy']) ? $dataPO['createdBy'] : '-';
                    $printUser = isset($dataPO['printUserName']) ? $dataPO['printUserName'] : '';

                    // Jika printUserName kosong atau berisi teks "Belum cetak/email"
                    if ($printUser == '' || $printUser == 'Belum cetak/email') {
                        echo $createdBy;
                    } else {
                        // Selain itu, tampilkan format: printUserName / createdBy
                        echo $printUser . ' / ' . $createdBy;
                    }
                ?>
            </td>
        </tr>
        <tr style="text-align:center; font-weight:bold;">
            <td>Buyer</td><td>Admin Buyer</td><td colspan="2"></td>
        </tr>
        <tr style="text-align:center;">
            <td style="padding-top:40px;">(__________________)</td>
            <td style="padding-top:40px;">(__________________)</td>
            <td colspan="2"></td>
        </tr>
    </table>
    <!-- FOOTER GRUP  -->


    <div style="border: 2px solid black; padding:8px; margin-top:15px; font-weight:bold;">
        PERHATIAN: Setelah barang dikirim, Supplier wajib mengambil SPB (Surat Penerimaan Barang), KECUALI Supplier Luar Kota
    </div>
</body>
</html>