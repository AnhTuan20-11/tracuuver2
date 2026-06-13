<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// 1. Lấy mã số thuế từ URL
$mst = isset($_GET['mst']) ? trim($_GET['mst']) : '';

if (empty($mst)) {
    echo json_encode(["success" => false, "message" => "Thiếu mã số thuế"]);
    exit;
}

// 2. Cấu hình cURL để gọi API đích
$url = "https://thongtincongty.vn/api/tax-code/request?taxCode=" . urlencode($mst) . "&returnTo=" . urlencode("/ma-so-thue");

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8"
]);

$html = curl_exec($ch);

// ĐÃ SỬA: Loại bỏ hàm curl_close() để tránh cảnh báo Deprecated trên PHP 8.5+

if (empty($html)) {
    echo json_encode(["success" => false, "message" => "Không tải được dữ liệu từ trang nguồn"]);
    exit;
}

// 3. Sử dụng thư viện DOM XPath để bóc tách dữ liệu
$dom = new DOMDocument();
// Ép DOMDocument đọc đúng định dạng UTF-8 để tránh lỗi font
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);

$tenCongTy = "";

// CHIẾN THUẬT 1: Quét tất cả các ô trong bảng dữ liệu <td>
$allTds = $dom->getElementsByTagName('td');
foreach ($allTds as $td) {
    $text = trim($td->nodeValue);
    
    // ĐÃ SỬA: Không dùng mb_strtoupper() nữa. 
    // Thay vào đó dùng hàm str_contains của PHP 8 để tìm chữ "CÔNG TY" trực tiếp (chấp nhận cả viết hoa viết thường)
    if ((str_contains($text, 'CÔNG TY') || str_contains($text, 'Công ty') || str_contains($text, 'công ty')) 
        && !str_contains($text, 'THONGTINCONGTY') && !str_contains($text, 'thongtincongty')) {
        $tenCongTy = $text;
        break;
    }
}

// CHIẾN THUẬT 2 (DỰ PHÒNG): Nếu quét bảng không ra, tìm trong các thẻ <h5>
if (empty($tenCongTy)) {
    $h5s = $dom->getElementsByTagName('h5');
    foreach ($h5s as $h5) {
        $text = trim($h5->nodeValue);
        if (!empty($text) && $text != $mst 
            && !str_contains($text, 'THONGTINCONGTY') && !str_contains($text, 'thongtincongty')) {
            $tenCongTy = $text;
            break;
        }
    }
}

// 4. Trả kết quả JSON sạch về cho giao diện index.html
if (!empty($tenCongTy)) {
    echo json_encode(["success" => true, "ten_cong_ty" => $tenCongTy], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["success" => false, "message" => "Không bóc tách được tên doanh nghiệp"]);
}
?>