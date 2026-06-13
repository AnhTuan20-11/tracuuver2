<?php
// Tắt hoàn toàn việc hiển thị các cảnh báo hệ thống (Deprecated/Warning) ra màn hình để tránh làm hỏng JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 1. Lấy mã số thuế từ URL gửi lên
$mst = isset($_GET['mst']) ? trim($_GET['mst']) : '';

if (empty($mst)) {
    echo json_encode(["success" => false, "message" => "Thiếu mã số thuế"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Cấu hình cURL gọi trang nguồn thongtincongty.vn
$url = "https://thongtincongty.vn/api/tax-code/request?taxCode=" . urlencode($mst) . "&returnTo=" . urlencode("/ma-so-thue");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");

$html = curl_exec($ch);

if (empty($html)) {
    echo json_encode(["success" => false, "message" => "Không tải được dữ liệu từ trang nguồn"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 3. CHIẾN THUẬT QUÉT XPATH CHUẨN XÁC THEO CÁC MỐC CHỮ MỚI
$tenCongTy = "Chưa cập nhật";
$nguoiDaiDien = "Chưa cập nhật";
$diaChi = "Chưa cập nhật";
$tenGiaoDich = "Chưa cập nhật";
$coQuanThue = "Chưa cập nhật";
$trangThai = "Chưa cập nhật";

// Khởi tạo DOM Document đọc mã UTF-8 sạch lỗi font
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);

// 1. Bóc Tên công ty từ thẻ <title>
if (preg_match('/<title>(.*?)<\/title>/iu', $html, $matches)) {
    $titleText = trim($matches[1]);
    $parts = explode(' - ', $titleText);
    if (count($parts) > 1) {
        $tenCongTy = explode('|', $parts[1])[0];
    } else {
        $tenCongTy = explode('|', $titleText)[0];
    }
}

// 2. Dùng XPath bốc "Người đại diện"
$nguoiDaiDienNode = $xpath->query("//td[contains(text(), 'Người đại diện') or contains(text(), 'Đại diện')]/following-sibling::td[1]");
if ($nguoiDaiDienNode->length > 0) {
    $nguoiDaiDien = trim($nguoiDaiDienNode->item(0)->nodeValue);
}

// 3. Dùng XPath bốc "Địa chỉ trụ sở"
$diaChiNode = $xpath->query("//td[contains(text(), 'Địa chỉ') or contains(text(), 'Trụ sở')]/following-sibling::td[1]");
if ($diaChiNode->length > 0) {
    $rawDiaChi = $diaChiNode->item(0)->nodeValue;
    if (str_contains($rawDiaChi, '- Căn cứ')) {
        $diaChi = trim(explode('- Căn cứ', $rawDiaChi)[0]);
    } else {
        $diaChi = trim($rawDiaChi);
    }
}

// 4. Dùng XPath bốc "Tên giao dịch" (Tên tiếng Anh)
$tenGiaoDichNode = $xpath->query("//td[contains(text(), 'Tên giao dịch')]/following-sibling::td[1]");
if ($tenGiaoDichNode->length > 0) {
    $tenGiaoDich = trim($tenGiaoDichNode->item(0)->nodeValue);
}

// 5. Dùng XPath bốc "Cơ quan thuế quản lý"
$coQuanThueNode = $xpath->query("//td[contains(text(), 'Cơ quan thuế')]/following-sibling::td[1]");
if ($coQuanThueNode->length > 0) {
    $coQuanThue = trim($coQuanThueNode->item(0)->nodeValue);
}

// 6. Dùng XPath bốc "Trạng thái hoạt động"
$trangThaiNode = $xpath->query("//td[contains(text(), 'Trạng thái')]/following-sibling::td[1]");
if ($trangThaiNode->length > 0) {
    $trangThai = trim($trangThaiNode->item(0)->nodeValue);
}

// Hàm dọn dẹp các ký tự khoảng trắng hoặc định dạng dư thừa ở đầu/cuối chuỗi
function clean_output($str) {
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    $str = str_replace(['\"', '\\'], ['', ''], $str);
    return trim($str, " :-,");
}

$tenCongTy = clean_output($tenCongTy);
$nguoiDaiDien = clean_output($nguoiDaiDien);
$diaChi = clean_output($diaChi);
$tenGiaoDich = clean_output($tenGiaoDich);
$coQuanThue = clean_output($coQuanThue);
$trangThai = clean_output($trangThai);

// 4. Trả kết quả JSON đầy đủ các trường mới về cho Frontend
echo json_encode([
    "success" => true, 
    "ten_cong_ty" => !empty($tenCongTy) ? $tenCongTy : "Chưa cập nhật",
    "nguoi_dai_dien" => !empty($nguoiDaiDien) ? $nguoiDaiDien : "Chưa cập nhật",
    "dia_chi" => !empty($diaChi) ? $diaChi : "Chưa cập nhật",
    "ten_giao_dich" => !empty($tenGiaoDich) ? $tenGiaoDich : "Chưa cập nhật",
    "co_quan_thue" => !empty($coQuanThue) ? $coQuanThue : "Chưa cập nhật",
    "trang_thai" => !empty($trangThai) ? $trangThai : "Chưa cập nhật"
], JSON_UNESCAPED_UNICODE);
exit;
?>