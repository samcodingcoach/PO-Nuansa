<?php
/**
 * PRINT PURCHASE ORDER - PURE DESIGN MODE
 * Mempertahankan 100% struktur asli tanpa koneksi database
 */
session_start();
?>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>PURCHASE ORDER</title>
</head>
<script src="../js/jquery-1.6.4.min.js" type="text/javascript"></script>
<script>
    $(document).ready(function () {
        window.print();
    });
</script>
<style>
div.b128{
    border-left: 1px black solid;
    height: 30px;
}   
</style>
<?php
    // Fungsi Barcode Asli dari file Anda
    global $char128asc,$char128charWidth;
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
        '114131','311141','411131','211412','211214','211232','23311120' );

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

    // Inisialisasi data dummy untuk pengetesan desain
    $row_getHeader = array(
        'DocDate' => '2026-04-25',
        'NSE_CH_Expired_Date' => '2026-05-25',
        'PONUMBER' => 'PO/2026/DUMMY',
        'VENDNAME' => 'PT. SUPPLIER CONTOH INDONESIA',
        'PURCHADDRESS1' => 'Jl. Pahlawan No. 123',
        'PURCHADDRESS2' => 'Kec. Sungai Pinang',
        'PURCHADDRESS3' => 'Kalimantan Timur',
        'PURCHCITY' => 'Samarinda',
        'PURCHPHONE1' => '0541-123456',
        'PURCHPHONE2' => '0812',
        'PURCHPHONE3' => '3456',
        'PURCHFAX' => '0541-654321',
        'PURCHCONTACT' => 'Bapak Budi Santoso',
        'CMPNYNAM' => 'NUANSA STORE SAMARINDA',
        'ADDRESS1' => 'Jl. Bhayangkara No. 01',
        'ADDRESS2' => '',
        'ADDRESS3' => '',
        'CITY' => 'Samarinda',
        'ExpAddress' => "EKSPEDISI CEPAT RAYA\nUP. Bapak Joko - 0811998877",
        'CMMTTEXT' => 'Kirim barang sebelum jam 10 pagi.',
        'USER2ENT' => 'SAMSU'
    );
?>
<body bgcolor="#FFFFFF">
    <div style="width:100%;">
        <table style="width:100%;">
            <tr>
                <td rowspan="4">
                    <img src="images/Nuansa.jpg" width="200" height="75" />
                </td>
                <td rowspan="2" style="font-weight:bold; font-size:16px;">
                    PURCHASE ORDER
                </td>
                <td>Tanggal PO:</td>
                <td>Tanggal Kirim:</td>
                <td>Tanggal Expired:</td>
            </tr>
            <tr>
                <td><? echo date('d-M-Y', strtotime($row_getHeader['DocDate']));?></td>
                <td><? echo date('d-M-Y', strtotime($row_getHeader['DocDate']));?></td>
                <td>
                    <? 
                    if($row_getHeader['NSE_CH_Expired_Date']!=NULL)
                    {
                        echo date('d-M-Y', strtotime($row_getHeader['NSE_CH_Expired_Date']));
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td rowspan="2">RE-PRINT</td>
                <td rowspan="2">
                    <? echo bar128(stripslashes($row_getHeader['PONUMBER']));?>
                </td>
                <td>NO. PO:</td>
                <td>T.O.P:</td>
            </tr>
            <tr>
                <td><? echo $row_getHeader['PONUMBER'];?></td>
                <td>-</td>
            </tr>
        </table>

        <table style="width:100%; padding:1px; border: 2px solid black; border-collapse: collapse;">
            <tr>
                <td style="width:50%; border: 2px solid black;">
                    <table>
                        <tr>
                            <td style="vertical-align:top; width:30%;">SUPPLIER:</td>
                            <td>
                                <? echo $row_getHeader['VENDNAME'];?><br />
                                <? echo $row_getHeader['PURCHADDRESS1'];?><br />
                                <? echo $row_getHeader['PURCHADDRESS2'];?><br />
                                <? echo $row_getHeader['PURCHADDRESS3'];?><br />
                                <? echo $row_getHeader['PURCHCITY'];?><br />
                            </td>
                        </tr>
                        <tr>
                            <td>TELEPON:</td>
                            <td><? echo $row_getHeader['PURCHPHONE1'].";".$row_getHeader['PURCHPHONE2'].";".$row_getHeader['PURCHPHONE3'];?></td>
                        </tr>
                        <tr>
                            <td>FAX:</td>
                            <td><? echo $row_getHeader['PURCHFAX'];?></td>
                        </tr>
                        <tr>
                            <td>EMAIL:</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>CONTACT:</td>
                            <td><? echo $row_getHeader['PURCHCONTACT'];?></td>
                        </tr>
                    </table>
                </td>
                <td style="width:50%; border: 2px solid black; vertical-align:top;">
                    <table>
                        <tr style="vertical-align:top;">
                            <td style="width:20%;">TOKO:</td>
                            <td>
                                <? echo $row_getHeader['CMPNYNAM'];?><br />
                                <? echo $row_getHeader['ADDRESS1'];?>
                                <? echo $row_getHeader['ADDRESS2'];?>
                                <? echo $row_getHeader['ADDRESS3'];?><br />
                                <? echo $row_getHeader['CITY'];?>
                            </td>
                        </tr>
                        <tr><td><br></td></tr>
                        <tr style="vertical-align:top;">
                            <td>
                                <? if (trim($row_getHeader['ExpAddress']) !== '') { echo 'EKSPEDISI:'; } ?>
                            </td>
                            <td><? echo nl2br($row_getHeader['ExpAddress']);?></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table style="width:100%; border-collapse: collapse;">
            <tr style="text-align:left; font-weight:bold; border-bottom: 1px solid black;">
                <td style="width:5%;">NO.</td>
                <td style="width:45%;">NAMA BARANG</td>
                <td style="width:15%;">SITE</td>
                <td style="width:10%;">Q</td>
                <td style="width:25%;">BARCODE</td>
            </tr>
            <? for($no=1; $no<=2; $no++) { ?>
            <tr>
                <td style="text-align:left;"><? echo $no;?></td>
                <td>CONTOH ITEM BARANG NOMOR <? echo $no; ?></td>
                <td>WH-01</td>
                <td>10</td>
                <td><? echo bar128("ITEM00".$no); ?></td>
            </tr>
            <? } ?>
        </table>

        <table style="width:100%;">
            <tr style="vertical-align:top;">
                <td colspan="3" style="width:80%;">
                    KET: <? echo $row_getHeader['CMMTTEXT'];?>
                </td>
                <td style="width:20%; text-align:left;">
                    TOTAL QTY: 20
                </td>
            </tr>
            <tr>
                <td style="text-align:center; padding-top:20px;">Mengetahui</td>
                <td>&nbsp;</td>
                <td>TIME: <? echo date("H:i:s");?></td>
                <td colspan="2">USER: <? echo $row_getHeader['USER2ENT'];?></td>
            </tr>
            <tr style="text-align:center;">
                <td>Buyer</td>
                <td>Admin Buyer</td>
            </tr>
            <tr style="text-align:center;">
                <td style="padding-top:50px;">
                    (__________________)
                </td>
                <td style="padding-top:50px;">
                    (__________________)
                </td>
            </tr>
        </table>
        <br />
        <div style="border: 2px solid black; padding:5px;">
            PERHATIAN:<br />
            Setelah barang dikirim, Supplier wajib mengambil SPB (Surat Penerimaan Barang), KECUALI Supplier Luar Kota
        </div>
    </div>
</body>
</html>