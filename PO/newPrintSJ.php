<?php
/**
 * PRINT PURCHASE ORDER - 100% DESIGN MATCH & FIXED DATA
 * Fix: Fatal Error on Barcode & Deep Nesting MTS
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
        
        // 1. Ambil Detail Vendor (Mapping: detailContact[0].name)
        $vNo = isset($dataPO['vendor']['vendorNo']) ? $dataPO['vendor']['vendorNo'] : null;
        if ($vNo) {
            $vRes = $api->getVendorDetail(null, $vNo);
            if (isset($vRes['data']['d'])) {
                $dataVendor = $vRes['data']['d'];
            }
        }

        // 2. Ambil Detail Branch (Toko MTS)
        $bId = isset($dataPO['branch']['id']) ? $dataPO['branch']['id'] : null;
        if ($bId) {
            $bRes = $api->getBranchDetail($bId);
            // Cek dua kemungkinan nesting (langsung d atau data -> d)
            if (isset($bRes['data']['d'])) {
                $dataBranch = $bRes['data']['d']; 
            } elseif (isset($bRes['data']['data']['d'])) {
                $dataBranch = $bRes['data']['data']['d'];
            }
        }
    }
}

// --- FUNGSI BARCODE 128 ASLI (DIPERBAIKI AGAR TIDAK FATAL ERROR) ---
global $char128asc, $char128wid;
$char128asc=' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';					
$char128wid = array('212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','23311120');

function bar128($text) {
    global $char128asc, $char128wid;
    if (empty($text)) return '';
    $sum = 104; // Inisialisasi sum
    $w = $char128wid[$sum];
    $onChar = 1;
    for($x=0; $x<strlen($text); $x++) {
        $pos = strpos($char128asc, $text[$x]);
        if ($pos !== false) {
            $w .= $char128wid[$pos];
            $sum += $onChar++ * $pos;
        }
    }
    $w .= $char128wid[$sum % 103] . $char128wid[106];
    $html = "<table cellpadding=0 cellspacing=0 border=0><tr>";
    for($x=0; $x<strlen($w); $x+=2) {
        $html .= "<td><div style=\"border-left:1px solid black; height:30px; border-left-width:{$w[$x]}; width:{$w[$x+1]}\"></div></td>";
    }
    return $html . "</tr></table>";
}
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>PURCHASE ORDER - <?php echo isset($dataPO['number']) ? $dataPO['number'] : ''; ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin: 0; padding: 10px; }
        .b128 { border-left: 1px black solid; height: 30px; }
        table { border-collapse: collapse; }
    </style>
</head>
<body bgcolor="#FFFFFF">
    <div style="width:100%;">
        <table style="width:100%;" border="0">
            <tr>
                <td rowspan="4" width="220">
                    <img src="images/Nuansa.jpg" width="200" height="75" />
                </td>
                <td rowspan="2" style="font-weight:bold; font-size:18px;">PURCHASE ORDER</td>
                <td>Tanggal PO:</td>
                <td>Tanggal Kirim:</td>
                <td>Tanggal Expired:</td>
            </tr>
            <tr>
                <td><?php echo isset($dataPO['transDate']) ? $dataPO['transDate'] : '-'; ?></td>
                <td><?php echo isset($dataPO['transDate']) ? $dataPO['transDate'] : '-'; ?></td>
                <td>
                    <?php 
                        if (isset($dataPO['transDate']) && isset($dataPO['autoCloseRange'])) {
                            $poDate = str_replace('/', '-', $dataPO['transDate']);
                            echo date('d-M-Y', strtotime($poDate . " + " . intval($dataPO['autoCloseRange']) . " days"));
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <td rowspan="2" valign="bottom">RE-PRINT</td>
                <td rowspan="2" valign="middle">
                    <?php if(!empty($dataPO['number'])) echo bar128($dataPO['number']); ?>
                </td>
                <td>NO. PO:</td>
                <td>T.O.P:</td>
            </tr>
            <tr>
                <td><?php echo isset($dataPO['number']) ? $dataPO['number'] : '-'; ?></td>
                <td>-</td>
            </tr>
        </table>

        <table style="width:100%; border: 2px solid black; margin-top:10px;" border="0">
            <tr>
                <td style="width:50%; border: 2px solid black; padding:5px; vertical-align:top;">
                    <table width="100%">
                        <tr><td width="30%">SUPPLIER:</td><td><strong><?php echo isset($dataPO['vendor']['name']) ? $dataPO['vendor']['name'] : '-'; ?></strong></td></tr>
                        <tr><td></td><td><?php echo isset($dataVendor['taxStreet']) ? $dataVendor['taxStreet'] : '-'; ?></td></tr>
                        <tr><td></td><td><?php echo isset($dataVendor['taxCity']) ? $dataVendor['taxCity'] : ''; ?></td></tr>
                        <tr><td>TELEPON:</td><td><?php echo isset($dataVendor['mobilePhone']) ? $dataVendor['mobilePhone'] : '-'; ?></td></tr>
                        <tr><td>CONTACT:</td><td><?php echo isset($dataVendor['detailContact'][0]['name']) ? $dataVendor['detailContact'][0]['name'] : '-'; ?></td></tr>
                    </table>
                </td>
                <td style="width:50%; border: 2px solid black; padding:5px; vertical-align:top;">
                    <table width="100%">
                        <tr><td width="20%">TOKO:</td><td><strong><?php echo isset($dataBranch['name']) ? $dataBranch['name'] : '-'; ?></strong></td></tr>
                        <tr><td></td><td><?php echo isset($dataBranch['address']) ? nl2br(trim($dataBranch['address'])) : '-'; ?></td></tr>
                        <tr><td><br>SHIP TO:</td><td><br><?php echo isset($dataPO['toAddress']) ? $dataPO['toAddress'] : '-'; ?></td></tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="width:100%; border-collapse: collapse; margin-top:10px;" border="1" cellpadding="5">
            <tr style="text-align:center; font-weight:bold; background-color:#eee;">
                <td width="5%">NO.</td>
                <td width="45%">NAMA BARANG</td>
                <td width="15%">SITE</td>
                <td width="10%">Q</td>
                <td width="25%">BARCODE</td>
            </tr>
            <tr><td colspan="5" align="center" style="height:100px; color:#999;">-- DETAIL KOSONG --</td></tr>
        </table>

        <table style="width:100%; margin-top:10px;">
            <tr>
                <td colspan="3">KET: <?php echo isset($dataPO['description']) ? $dataPO['description'] : '-'; ?></td>
                <td align="right">TOTAL QTY: -</td>
            </tr>
            <tr style="text-align:center;">
                <td style="padding-top:20px;">Mengetahui</td>
                <td>&nbsp;</td>
                <td style="padding-top:20px;">TIME: <?php echo date("H:i:s"); ?></td>
                <td style="padding-top:20px;">USER: <?php echo isset($dataPO['createdBy']) ? $dataPO['createdBy'] : '-'; ?></td>
            </tr>
            <tr style="text-align:center; font-weight:bold;">
                <td>Buyer</td>
                <td>Admin Buyer</td>
                <td colspan="2"></td>
            </tr>
            <tr style="text-align:center;">
                <td style="padding-top:10px;">
                    ( <img src="img/<?php echo isset($dataPO['createdBy']) ? $dataPO['createdBy'] : 'default'; ?>.jpg" width="80" height="80" onerror="this.style.visibility='hidden'" /> )
                </td>
                <td style="padding-top:10px;">
                    ( <img src="img/<?php echo isset($dataPO['createdBy']) ? $dataPO['createdBy'] : 'default'; ?>.jpg" width="80" height="80" onerror="this.style.visibility='hidden'" /> )
                </td>
                <td colspan="2"></td>
            </tr>
        </table>

        <div style="border: 2px solid black; padding:5px; margin-top:10px;">
            PERHATIAN:<br />
            Setelah barang dikirim, Supplier wajib mengambil SPB (Surat Penerimaan Barang), KECUALI Supplier Luar Kota
        </div>
    </div>
    <script type="text/javascript">window.print();</script>
</body>
</html>