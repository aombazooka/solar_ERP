<?php
/**
 * gen_manual.php — สร้างคู่มือการใช้งาน SolarSell เป็นไฟล์ Word (.docx)
 * รันผ่าน CLI:  C:\xampp\php\php.exe tools\gen_manual.php
 * ไม่ต้องใช้ ZipArchive — มี MiniZip (store method) ในตัว
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit('CLI only'); }

/* ───────────────── MiniZip: ZIP แบบ store (ไม่บีบอัด) ───────────────── */
final class MiniZip {
    private array $files = [];
    public function add(string $name, string $data): void { $this->files[$name] = $data; }
    public function build(): string {
        $local = ''; $central = ''; $offset = 0;
        foreach ($this->files as $name => $data) {
            $crc = crc32($data); $len = strlen($data);
            $nameB = $name;
            $lh = "\x50\x4b\x03\x04" . pack('v',20) . pack('v',0) . pack('v',0)
                . pack('v',0) . pack('v',0) . pack('V',$crc) . pack('V',$len) . pack('V',$len)
                . pack('v',strlen($nameB)) . pack('v',0) . $nameB;
            $local .= $lh . $data;
            $central .= "\x50\x4b\x01\x02" . pack('v',20) . pack('v',20) . pack('v',0) . pack('v',0)
                . pack('v',0) . pack('v',0) . pack('V',$crc) . pack('V',$len) . pack('V',$len)
                . pack('v',strlen($nameB)) . pack('v',0) . pack('v',0) . pack('v',0) . pack('v',0)
                . pack('V',0) . pack('V',$offset) . $nameB;
            $offset += strlen($lh) + $len;
        }
        $eocd = "\x50\x4b\x05\x06" . pack('v',0) . pack('v',0)
            . pack('v',count($this->files)) . pack('v',count($this->files))
            . pack('V',strlen($central)) . pack('V',$offset) . pack('v',0);
        return $local . $central . $eocd;
    }
}

/* ───────────────── ตัวช่วยสร้าง WordprocessingML ───────────────── */
function x(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }

/** run (ข้อความ) — รองรับ bold/color/size */
function run(string $text, array $o = []): string {
    $rpr = '';
    if (!empty($o['b'])) $rpr .= '<w:b/><w:bCs/>';
    if (!empty($o['i'])) $rpr .= '<w:i/><w:iCs/>';
    if (!empty($o['color'])) $rpr .= '<w:color w:val="'.$o['color'].'"/>';
    if (!empty($o['sz'])) $rpr .= '<w:sz w:val="'.$o['sz'].'"/><w:szCs w:val="'.$o['sz'].'"/>';
    if (!empty($o['font'])) $rpr .= '<w:rFonts w:ascii="'.$o['font'].'" w:hAnsi="'.$o['font'].'" w:cs="'.$o['font'].'"/>';
    $rpr = $rpr ? "<w:rPr>$rpr</w:rPr>" : '';
    return "<w:r>$rpr<w:t xml:space=\"preserve\">".x($text)."</w:t></w:r>";
}
/** ย่อหน้าจาก runs[] */
function para(string $runs, string $ppr = ''): string { return "<w:p>$ppr$runs</w:p>"; }

function h1(string $t): string { return para(run($t), '<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>'); }
function h2(string $t): string { return para(run($t), '<w:pPr><w:pStyle w:val="Heading2"/></w:pPr>'); }
function h3(string $t): string { return para(run($t), '<w:pPr><w:pStyle w:val="Heading3"/></w:pPr>'); }
function p(string $t): string  { return para(run($t)); }
/** ย่อหน้าที่มีหลาย run (รับ array ของ [text, opts]) */
function pmix(array $parts): string { $r=''; foreach($parts as $pp){ $r.=run($pp[0], $pp[1]??[]); } return para($r); }

function bullet(string $t, int $lvl = 0): string {
    $ind = 360 + $lvl*360;
    $ppr = '<w:pPr><w:ind w:left="'.($ind+360).'" w:hanging="360"/><w:spacing w:after="40"/></w:pPr>';
    return para(run('•   ', ['color'=>'EA580C']).run($t), $ppr);
}
function step(int $n, string $t): string {
    $ppr = '<w:pPr><w:ind w:left="720" w:hanging="360"/><w:spacing w:after="40"/></w:pPr>';
    return para(run($n.'.   ', ['b'=>true,'color'=>'D97706']).run($t), $ppr);
}
function note(string $t): string {
    $ppr = '<w:pPr><w:pBdr><w:left w:val="single" w:sz="18" w:space="6" w:color="F59E0B"/></w:pBdr><w:ind w:left="180"/><w:spacing w:before="60" w:after="120"/></w:pPr>';
    return para(run('💡 ', []).run($t, ['i'=>true]), $ppr);
}
/** กรอบแทนภาพหน้าจอ */
function placeholder(string $caption, string $path): string {
    $ppr = '<w:pPr><w:pBdr>'
        .'<w:top w:val="dashed" w:sz="8" w:space="6" w:color="9CA3AF"/>'
        .'<w:bottom w:val="dashed" w:sz="8" w:space="6" w:color="9CA3AF"/>'
        .'<w:left w:val="dashed" w:sz="8" w:space="6" w:color="9CA3AF"/>'
        .'<w:right w:val="dashed" w:sz="8" w:space="6" w:color="9CA3AF"/></w:pBdr>'
        .'<w:shd w:val="clear" w:fill="F3F4F6"/><w:jc w:val="center"/>'
        .'<w:spacing w:before="120" w:after="40" w:line="360" w:lineRule="auto"/></w:pPr>';
    $r = run('📷  ภาพหน้าจอ: ', ['b'=>true,'color'=>'6B7280']).run($caption, ['color'=>'374151'])
        .run('     (แคปจากหน้า ', ['color'=>'9CA3AF']).run($path, ['color'=>'2563EB','font'=>'Consolas'])
        .run(' แล้ววางแทนกรอบนี้)', ['color'=>'9CA3AF']);
    return para($r, $ppr);
}
function pageBreak(): string { return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>'; }
function spacer(): string { return '<w:p><w:pPr><w:spacing w:after="0"/></w:pPr></w:p>'; }

/** ตาราง: $headers[], $rows[] (แต่ละแถวเป็น array ของ cell string) ; $widths[] DXA */
function table(array $headers, array $rows, array $widths): string {
    $total = array_sum($widths);
    $grid = ''; foreach ($widths as $w) $grid .= '<w:gridCol w:w="'.$w.'"/>';
    $cellBorder = '<w:tcBorders>'
        .'<w:top w:val="single" w:sz="4" w:color="D1D5DB"/><w:bottom w:val="single" w:sz="4" w:color="D1D5DB"/>'
        .'<w:left w:val="single" w:sz="4" w:color="D1D5DB"/><w:right w:val="single" w:sz="4" w:color="D1D5DB"/></w:tcBorders>';
    $mkCell = function(string $text, int $w, bool $head) use ($cellBorder) {
        $shd = $head ? '<w:shd w:val="clear" w:fill="1F2937"/>' : '';
        $rpr = $head ? ['b'=>true,'color'=>'FFFFFF'] : [];
        $tcpr = '<w:tcPr><w:tcW w:w="'.$w.'" w:type="dxa"/>'.$cellBorder.$shd
            .'<w:tcMar><w:top w:w="40" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tcMar></w:tcPr>';
        // อนุญาตหลายบรรทัดในเซลล์ด้วย \n
        $lines = explode("\n", $text); $body='';
        foreach ($lines as $i=>$ln) { $body .= '<w:p><w:pPr><w:spacing w:after="0"/></w:pPr>'.run($ln,$rpr).'</w:p>'; }
        return '<w:tc>'.$tcpr.$body.'</w:tc>';
    };
    $out = '<w:tbl><w:tblPr><w:tblW w:w="'.$total.'" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
        .'<w:tblBorders><w:top w:val="single" w:sz="4" w:color="D1D5DB"/><w:bottom w:val="single" w:sz="4" w:color="D1D5DB"/>'
        .'<w:left w:val="single" w:sz="4" w:color="D1D5DB"/><w:right w:val="single" w:sz="4" w:color="D1D5DB"/>'
        .'<w:insideH w:val="single" w:sz="4" w:color="E5E7EB"/><w:insideV w:val="single" w:sz="4" w:color="E5E7EB"/></w:tblBorders></w:tblPr>'
        .'<w:tblGrid>'.$grid.'</w:tblGrid>';
    // header row
    $hr=''; foreach ($headers as $i=>$hh) $hr .= $mkCell($hh, $widths[$i], true);
    $out .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>'.$hr.'</w:tr>';
    foreach ($rows as $row) { $tr=''; foreach ($row as $i=>$c) $tr .= $mkCell((string)$c, $widths[$i], false); $out .= '<w:tr>'.$tr.'</w:tr>'; }
    return $out.'</w:tbl>'.spacer();
}

/* ───────────────── เนื้อหาคู่มือ ───────────────── */
require __DIR__ . '/manual_content.php';   // คืนค่าตัวแปร $BODY (string ของ XML)

/* ───────────────── ประกอบ document.xml ───────────────── */
$sectPr = '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1134" w:bottom="1440" w:left="1134" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>';
$documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
.'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
.'<w:body>'.$BODY.$sectPr.'</w:body></w:document>';

$FONT = 'Tahoma';
$stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
.'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
.'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="'.$FONT.'" w:hAnsi="'.$FONT.'" w:cs="'.$FONT.'"/><w:sz w:val="21"/><w:szCs w:val="21"/><w:lang w:bidi="th-TH"/></w:rPr></w:rPrDefault></w:docDefaults>'
.'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:pPr><w:spacing w:after="100" w:line="276" w:lineRule="auto"/></w:pPr></w:style>'
.'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:pPr><w:spacing w:after="120"/><w:jc w:val="center"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="D97706"/><w:sz w:val="56"/><w:szCs w:val="56"/></w:rPr></w:style>'
.'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="280" w:after="140"/><w:outlineLvl w:val="0"/><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="3" w:color="F59E0B"/></w:pBdr></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="B45309"/><w:sz w:val="34"/><w:szCs w:val="34"/></w:rPr></w:style>'
.'<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="220" w:after="100"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="1F2937"/><w:sz w:val="27"/><w:szCs w:val="27"/></w:rPr></w:style>'
.'<w:style w:type="paragraph" w:styleId="Heading3"><w:name w:val="heading 3"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:spacing w:before="160" w:after="60"/><w:outlineLvl w:val="2"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="EA580C"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr></w:style>'
.'</w:styles>';

$contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
.'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
.'<Default Extension="xml" ContentType="application/xml"/>'
.'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
.'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
.'</Types>';

$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
.'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
.'</Relationships>';

$docRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
.'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
.'</Relationships>';

$zip = new MiniZip();
$zip->add('[Content_Types].xml', $contentTypes);
$zip->add('_rels/.rels', $rels);
$zip->add('word/document.xml', $documentXml);
$zip->add('word/styles.xml', $stylesXml);
$zip->add('word/_rels/document.xml.rels', $docRels);

$out = __DIR__ . '/../คู่มือการใช้งาน_SolarSell.docx';
file_put_contents($out, $zip->build());
echo "สร้างไฟล์เรียบร้อย: " . realpath($out) . PHP_EOL;
echo "ขนาด: " . number_format(filesize($out)/1024, 1) . " KB" . PHP_EOL;
