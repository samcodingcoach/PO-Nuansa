<?
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
<?
	include("../Connection/connect.php");
	
	global $char128asc,$char128charWidth;
	$char128asc=' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~';					
	$char128wid = array(
		'212222','222122','222221','121223','121322','131222','122213','122312','132212','221213', // 0-9 
		'221312','231212','112232','122132','122231','113222','123122','123221','223211','221132', // 10-19 
		'221231','213212','223112','312131','311222','321122','321221','312212','322112','322211', // 20-29 			
		'212123','212321','232121','111323','131123','131321','112313','132113','132311','211313', // 30-39 
		'231113','231311','112133','112331','132131','113123','113321','133121','313121','211331', // 40-49 
		'231131','213113','213311','213131','311123','311321','331121','312113','312311','332111', // 50-59 
		'314111','221411','431111','111224','111422','121124','121421','141122','141221','112214', // 60-69 
		'112412','122114','122411','142112','142211','241211','221114','413111','241112','134111', // 70-79 
		'111242','121142','121241','114212','124112','124211','411212','421112','421211','212141', // 80-89 
		'214121','412121','111143','111341','131141','114113','114311','411113','411311','113141', // 90-99
		'114131','311141','411131','211412','211214','211232','23311120'   );					   // 100-106
	
	////Define Function
	function bar128($text) {						// Part 1, make list of widths
	  global $char128asc,$char128wid;				
	  $w = $char128wid[$sum = 104];							// START symbol
	  $onChar=1;
	  for($x=0;$x<strlen($text);$x++)								// GO THRU TEXT GET LETTERS
		if (!( ($pos = strpos($char128asc,$text[$x])) === false )){	// SKIP NOT FOUND CHARS
		  $w.= $char128wid[$pos];
		  $sum += $onChar++ * $pos;
		}					
	  $w.= $char128wid[ $sum % 103 ].$char128wid[106];  		//Check Code, then END
													//Part 2, Write rows
	  $html="<table cellpadding=0 cellspacing=0><tr>";				
	  for($x=0;$x<strlen($w);$x+=2)   						// code 128 widths: black border, then white space
		$html .= "<td><div class=\"b128\" style=\"border-left-width:{$w[$x]};width:{$w[$x+1]}\"></div>";	
	  //return "$html<tr><td  colspan=".strlen($w)." align=center><font family=arial size=2><b>$text</table>";		
	  return "$html<tr><td  colspan=".strlen($w)." align=center><font family=arial size=2><b></table>";		
	}


	date_default_timezone_set('Etc/GMT-8');

	if(!isset($_REQUEST['nomor_po']))
	{
		$nomor_po="PO/1307/00496";
	}
	else
	{
		$nomor_po=$_REQUEST['nomor_po'];
	}
	
	$query_getHeader="select a.PONUMBER,
					a.DocDate,
					a.VENDNAME,
					a.PURCHADDRESS1,
					a.PURCHADDRESS2,
					a.PURCHADDRESS3,
					a.PURCHCITY,
					a.PURCHPHONE1,
					a.PURCHPHONE2,
					a.PURCHPHONE3,
					a.PURCHCONTACT,
					a.PURCHFAX,
					a.CMPNYNAM,
					a.ADDRESS1,
					a.ADDRESS2,
					a.ADDRESS3,
					a.CITY,
					a.SUBTOTAL,
					a.TAXAMNT,
					a.USER2ENT,
					a.CMMTTEXT,
					(
						select NSE_CH_Expired_Date from NSECH018 b
						where a.PONUMBER=b.PONUMBER
					)
					as NSE_CH_Expired_Date
					, a.ExpAddress /*TAMBAHAN*/
					from PO_Header a
					where a.PONUMBER='".$nomor_po."'";
	$res_getHeader=mysql_query($query_getHeader);
	//echo $query_getHeader;
	$row_getHeader=mysql_fetch_array($res_getHeader);
?>
<body bgcolor="#FFFFFF">
	<div style="width:100%;">
        <table style="width:100%;">
            <tr>
                <td rowspan="4">
                    <img src="../lib-img/Nuansa.jpg" width="200" height="75" />
                </td>
                <td rowspan="2">
                    PURCHASE ORDER
                </td>
                <td>
                    Tanggal PO:
                </td>
                <td>
                    Tanggal Kirim:
                </td>
                <td>
                    Tanggal Expired:
                </td>
            </tr>
            <tr>
            	<td>
                	<? echo date('d-M-Y', strtotime($row_getHeader['DocDate']));?>
                </td>
            	<td>
                	<? echo date('d-M-Y', strtotime($row_getHeader['DocDate']));?>
                </td>
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
            	<td rowspan="2">
                	RE-PRINT
                </td>
                <td rowspan="2">
                	<? echo bar128(stripslashes($row_getHeader['PONUMBER']));?>
                </td>
            	<td>
                	NO. PO:
                </td>
            	<td>
                	T.O.P:
                </td>
            </tr>
            <tr>
            	<td>
                	<? echo $row_getHeader['PONUMBER'];?>
                </td>
            	<td>
                	-
                </td>
            </tr>
        </table>
        <table style="width:100%; padding:1px; border: 2px solid black;">
        	<tr>
            	<td style="width:50%; border: 2px solid black;">
                	<table>
                    	<tr>
                        	<td style="vertical-align:top; width:30%;">
                            	SUPPLIER:
                            </td>
                        	<td>
                				<? echo $row_getHeader['VENDNAME'];?><br />
                                <? echo $row_getHeader['PURCHADDRESS1'];?><br />
                                <? echo $row_getHeader['PURCHADDRESS2'];?><br />
                                <? echo $row_getHeader['PURCHADDRESS3'];?><br />
                                <? echo $row_getHeader['PURCHCITY'];?><br />
                            </td>
                        </tr>
                    	<tr>
                        	<td>
                            	TELEPON:
                            </td>
                        	<td>
                            	<? echo $row_getHeader['PURCHPHONE1'].";".$row_getHeader['PURCHPHONE2'].";".$row_getHeader['PURCHPHONE3'];?>
                            </td>
                        </tr>
                    	<tr>
                        	<td>
                            	FAX:
                            </td>
                        	<td>
                            	<? echo $row_getHeader['PURCHFAX'];?>
                            </td>
                        </tr>
                    	<tr>
                        	<td>
                            	EMAIL:
                            </td>
                        	<td>
                            	-
                            </td>
                        </tr>
                    	<tr>
                        	<td>
                            	CONTACT:
                            </td>
                        	<td>
                            	<? echo $row_getHeader['PURCHCONTACT'];?>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="width:50%; border: 2px solid black; vertical-align:top;">
                	<table>
                    	<tr style="vertical-align:top;">
                        	<td style="width:20%;">
                            	TOKO:
                            </td>
                        	<td>
                            	<? echo $row_getHeader['CMPNYNAM'];?><br />
                            	<? echo $row_getHeader['ADDRESS1'];?>
                            	<? echo $row_getHeader['ADDRESS2'];?>
                            	<? echo $row_getHeader['ADDRESS3'];?><br />
                            	<? echo $row_getHeader['CITY'];?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <br>
                            </td>
                        </tr>
                    	<tr style="vertical-align:top;">
                        	<td>
                        	    <?
                        	    if (trim($row_getHeader['ExpAddress']) !== '') {
                            	    echo 'EKSPEDISI:';
                        	    }
                            	?>
                            </td>
                        	<td>
                            	<? echo nl2br($row_getHeader['ExpAddress']);?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <table style="width:100%;">
        	<tr style="text-align:left;">
            	<td>
                	NO.
                </td>
            	<td>
                	NAMA BARANG
                </td>
            	<td>
                	SITE
                </td>
            	<td>
                	Q
                </td>
            	<td>
                	BARCODE
                </td>
            </tr>
            <?
			$no=0;
			$jum_qyy=0;
            $query_getDetail="Select	POS_Barcode_ID, PONUMBER, ITEMNMBR as Item_Number, ITEMDESC as Item_Description,
										LOCNCODE as SiteID, QtyOrder, UOFM, UNITCOST as Unit_Cost, EXTDCOST as Total
								from	PO_Detail
								where 	PONUMBER='".$nomor_po."'
								Order By 1,3
							";
			$res_getDetail=mysql_query($query_getDetail);
			while($row_getDetail=mysql_fetch_array($res_getDetail))
			{
				$no+=1;
				$jum_qyy+=$row_getDetail['QtyOrder'];
			?>
        	<tr>
            	<td style="text-align:left;">
                	<? echo $no;?>
                </td>
            	<td>
                	<? echo $row_getDetail['Item_Description'];?>
                </td>
            	<td>
                	<? echo $row_getDetail['SiteID'];?>
                </td>
            	<td>
                	<? echo number_format($row_getDetail['QtyOrder']);?>
                </td>
            	<td><? //echo $row_getDetail['POS_Barcode_ID'];?>
                	<? if($row_getDetail['POS_Barcode_ID']==""){}else{echo bar128(stripslashes($row_getDetail['POS_Barcode_ID']));}?>
                </td>
            </tr>
            <?
			}
			?>
        </table>
        <table style="width:100%;">
        	<tr style="vertical-align:top;">
            	<td colspan="3" style="width:80%;">
                	KET: <? echo $row_getHeader['CMMTTEXT'];?>
                </td>
            	<td style="width:20%; text-align:left;">
                	TOTAL QTY: <? echo number_format($jum_qyy);?>
                </td>
            </tr>
            <tr>
            	<td style="text-align:center; padding-top:20px;">
                	Mengetahui
                </td>
                <td>&nbsp;
                	
                </td>
            	<td>
                	TIME: <? echo date("H:i:s");?>
                </td>
                <td colspan="2">
                	USER: <? echo $row_getHeader['USER2ENT'];?>
                </td>
            </tr>
            <tr style="text-align:center;">
            	<td>
                	Buyer
                </td>
            	<td>
                	Admin Buyer
                </td>
            </tr>
            <tr style="text-align:center;">
            	<td style="padding-tops:50px;">
                	<!--(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)-->
                    (<img src="img/<? echo $row_getHeader['USER2ENT'];?>.jpg" width="100" height="100" />)
                </td>
            	<td style="">
                	(<img src="img/<? echo $row_getHeader['USER2ENT'];?>.jpg" width="100" height="100" />)
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