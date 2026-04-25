<?php
/**
 * PRINT PURCHASE ORDER - FINAL API MAPPING
 * Perbaikan: Contact (Array Index), Branch Name, Expired Calc, & Image Path
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
        
        // 1. Get Detail Vendor
        $vNo = isset($dataPO['vendor']['vendorNo']) ? $dataPO['vendor']['vendorNo'] : null;
        if ($vNo) {
            $vRes = $api->getVendorDetail(null, $vNo);
            if ($vRes['success'] && isset($vRes['data']['d'])) {
                $dataVendor = $vRes['data']['d'];
            }
        }

        // 2. Get Detail Branch
        $bId = isset($dataPO['branch']['id']) ? $dataPO['branch']['id'] : null;
        if ($bId) {
            $bRes = $api->getBranchDetail($bId);
            if ($bRes['success'] && isset($bRes['data']['d'])) {
                $dataBranch = $bRes['data']['d'];
            }
        }
    }
}

// --- FUNGSI BARCODE ASLI ---
global $char128asc, $char128wid;
$char128asc=' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';					
$char128wid = array(
    '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
    '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
    '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
    '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
    '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
    '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
    '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
    '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
    '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
    '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
    '114131','311141','411131','211412','211214','211232','23311120'
);

function bar128($text) {
  global $char128asc,$char128wid;               
  $w = $char128wid[$sum = 104];
  $onChar=1;
  for($x=0;$x<strlen($text);$x++)
    if (!( ($pos = strpos($char128asc,$text[$x])) === false )){
      $w.= $char128wid[$pos];
      $sum += $onChar++ * $pos;
    }                   
  $w.= $char128wid[ $sum % 103 ].$char128wid[106];
  $html="<table cellpadding=0 cellspacing=0><tr>";              
  for($x=0;$x<strlen($w);$x+=2)
    $html .= "<td><div class=\"b128\" style=\"border-left-width:{$w[$x]};width:{$w[$x+1]}\"></div>";    
  return "$html<tr><td colspan=".strlen($w)." align=center><font family=arial size=2><b></table>";     
}
?>

<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>PURCHASE ORDER - <?php echo isset($dataPO['number']) ? $dataPO['number'] : ''; ?></title>
<script src="../js/jquery-1.6.4.min.js" type="text/javascript"></script>
<script>
    $(document).ready(function () {
        window.print();
    });
</script>
<style>
div.b128{ border-left: 1px black solid; height: 30px; }   
body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
</style>
</head>

<body bgcolor="#FFFFFF">
    <div style="width:100%;">
        <table style="width:100%;">
            <tr>
                <td rowspan="4">
                    <img src="images/Nuansa.jpg" width="200" height="75" />
                </td>
                <td rowspan="2" style="font-weight:bold; font-size:16px; vertical-align:middle;">
                    PURCHASE ORDER
                </td>
                <td>Tanggal PO:</td>
                <td>Tanggal Kirim:</td>
                <td>Tanggal Expired:</td>
            </tr>
            <tr>
                <td><?php echo isset($dataPO['transDate']) ? $dataPO['transDate'] : '-'; ?></td>
                <td><?php echo isset($dataPO['shipDateView']) ? $dataPO['shipDateView'] : '-'; ?></td>
                <td>
                    <?php 
                        if (isset($dataPO['transDate']) && isset($dataPO['autoCloseRange'])) {
                            // Normalisasi format tanggal untuk strtotime
                            $poDate = str_replace('/', '-', $dataPO['transDate']);
                            $days = intval($dataPO['autoCloseRange']);
                            echo date('d-M-Y', strtotime($poDate . " + $days days"));
                        } else {
                            echo '-';
                        }
                    ?>
                </td>
            </tr>
            <tr>
                <td rowspan="2" style="vertical-align:bottom;">RE-PRINT</td>
                <td rowspan="2">
                    <?php if(isset($dataPO['number'])) echo bar128(stripslashes($dataPO['number'])); ?>
                </td>
                <td>NO. PO:</td>
                <td>T.O.P:</td>
            </tr>
            <tr>
                <td><?php echo isset($dataPO['number']) ? $dataPO['number'] : '-'; ?></td>
                <td>-</td>
            </tr>
        </table>

        <table style="width:100%; padding:1px; border: 2px solid black; border-collapse: collapse;">
            <tr>
                <td style="width:50%; border: 2px solid black;">
                    <table style="width:100%;">
                        <tr>
                            <td style="vertical-align:top; width:30%;">SUPPLIER:</td>
                            <td>
                                <strong><?php echo isset($dataPO['vendor']['name']) ? $dataPO['vendor']['name'] : '-'; ?></strong><br />
                                <?php echo isset($dataVendor['taxStreet']) ? $dataVendor['taxStreet'] : '-'; ?><br />
                                <?php echo isset($dataVendor['taxCity']) ? $dataVendor['taxCity'] : ''; ?><br />
                            </td>
                        </tr>
                        <tr>
                            <td>TELEPON:</td>
                            <td><?php echo isset($dataVendor['mobilePhone']) ? $dataVendor['mobilePhone'] : '-'; ?></td>
                        </tr>
                        <tr>
                            <td>FAX:</td>
                            <td><?php echo isset($dataVendor['fax']) ? $dataVendor['fax'] : '-'; ?></td>
                        </tr>
                        <tr>
                            <td>EMAIL:</td>
                            <td><?php echo isset($dataVendor['email']) ? $dataVendor['email'] : '-'; ?></td>
                        </tr>
                        <tr>
                            <td>CONTACT:</td>
                            <td>
                                <?php 
                                    // Sesuai struktur JSON: detailContact adalah array, ambil indeks 0
                                    echo isset($dataVendor['detailContact'][0]['name']) ? $dataVendor['detailContact'][0]['name'] : '-'; 
                                ?>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width:50%; border: 2px solid black; vertical-align:top;">
                    <table style="width:100%;">
                        <tr style="vertical-align:top;">
                            <td style="width:20%;">TOKO:</td>
                            <td>
                                <strong><?php echo isset($dataBranch['name']) ? $dataBranch['name'] : '-'; ?></strong><br />
                                <?php echo isset($dataBranch['city']) ? $dataBranch['city'] : ''; ?><br />
                                <br />
                                <strong>SHIP TO:</strong><br />
                                <?php echo isset($dataPO['toAddress']) ? $dataPO['toAddress'] : '-'; ?>
                            </td>
                        </tr>
                        <tr style="vertical-align:top;">
                            <td>EKSPEDISI:</td>
                            <td><?php echo isset($dataPO['description']) ? nl2br($dataPO['description']) : '-'; ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="width:100%; border-collapse: collapse; margin-top:10px;" border="1">
            <tr style="text-align:left; font-weight:bold; background-color:#eee;">
                <td style="width:5%; padding:5px;">NO.</td>
                <td style="width:45%; padding:5px;">NAMA BARANG</td>
                <td style="width:15%; padding:5px;">SITE</td>
                <td style="width:10%; padding:5px;">Q</td>
                <td style="width:25%; padding:5px;">BARCODE</td>
            </tr>
            <tr>
                <td colspan="5" align="center" style="padding:40px; color:#999; font-style:italic;">
                    -- DETAIL ITEM KOSONG --
                </td>
            </tr>
        </table>

        <table style="width:100%; margin-top:10px;">
            <tr style="vertical-align:top;">
                <td colspan="3" style="width:80%;">
                    KET: <?php echo isset($dataPO['description']) ? $dataPO['description'] : '-'; ?>
                </td>
                <td style="width:20%; text-align:left;">
                    TOTAL QTY: -
                </td>
            </tr>
            <tr>
                <td style="text-align:center; padding-top:20px;">Mengetahui</td>
                <td>&nbsp;</td>
                <td>TIME: <?php echo date("H:i:s"); ?></td>
                <td colspan="2">USER: <?php echo isset($dataPO['createdBy']) ? $dataPO['createdBy'] : '-'; ?></td>
            </tr>
            <tr style="text-align:center;">
                <td>Buyer</td>
                <td>Admin Buyer</td>
            </tr>
            <tr style="text-align:center;">
                <td style="padding-top:40px;">(__________________)</td>
                <td style="padding-top:40px;">(__________________)</td>
            </tr>
        </table>
    </div>
</body>
</html>