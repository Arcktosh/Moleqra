<?php

declare(strict_types=1);

function simple_pdf_escape(string $text): string
{
    if(function_exists('iconv')){$converted=@iconv('UTF-8','Windows-1252//TRANSLIT//IGNORE',$text);if(is_string($converted))$text=$converted;}
    return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$text);
}

function simple_pdf_document(array $lines,string $title='Moleqra'): string
{
    $content="BT\n/F1 18 Tf\n50 790 Td\n(".simple_pdf_escape($title).") Tj\n0 -28 Td\n/F1 10 Tf\n";
    foreach($lines as $line){$content.='('.simple_pdf_escape((string)$line).") Tj\n0 -16 Td\n";}
    $content.="ET\n";
    $objects=[];
    $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2]='<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $objects[3]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';
    $objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[5]='<< /Length '.strlen($content).' >>' . "\nstream\n".$content."endstream";
    $pdf="%PDF-1.4\n";$offsets=[0=>0];
    for($i=1;$i<=5;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".$objects[$i]."\nendobj\n";}
    $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";for($i=1;$i<=5;$i++)$pdf.=sprintf('%010d 00000 n ',$offsets[$i])."\n";
    $pdf.="trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
}
