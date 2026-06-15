<?php
// Tắt hoàn toàn việc hiển thị các cảnh báo hệ thống (Deprecated/Warning) ra màn hình để tránh làm hỏng JSON
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// ==========================================================
// CẤU HÌNH KẾT NỐI DATABASE
// Lấy thông tin này trong cPanel > MySQL Databases
// ==========================================================
$db_host = "sql211.infinityfree.com";              // hoặc dạng sqlXXX.epizy.com tùy server
$db_name = "if0_42184286_prm";     // tên database
$db_user = "if0_42184286";           // username database
$db_pass = "k1Hw1GeNoTFC";          // password database

// Số ngày dữ liệu cache còn được coi là "mới" trước khi tra cứu lại từ nguồn
$cache_days = 30;

// 1. Lấy mã số thuế từ URL gửi lên
$mst = isset($_GET['mst']) ? trim($_GET['mst']) : '';

if (empty($mst)) {
    echo json_encode(["success" => false, "message" => "Thiếu mã số thuế"], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==========================================================
// KẾT NỐI DATABASE (nếu lỗi, vẫn cho web hoạt động bình thường, chỉ là không cache)
// ==========================================================
$pdo = null;
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    $pdo = null;
}

// ==========================================================
// 2. KIỂM TRA CACHE TRƯỚC - nếu có và còn mới thì trả về luôn
// ==========================================================
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM mst_cache WHERE mst = ?");
        $stmt->execute([$mst]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            $soNgayDaQua = (time() - strtotime($cached['updated_at'])) / 86400;

            if ($soNgayDaQua <= $cache_days) {
                echo json_encode([
                    "success" => true,
                    "ten_cong_ty" => $cached['ten_cong_ty'],
                    "nguoi_dai_dien" => $cached['nguoi_dai_dien'],
                    "dia_chi" => $cached['dia_chi'],
                    "ten_giao_dich" => $cached['ten_giao_dich'],
                    "co_quan_thue" => $cached['co_quan_thue'],
                    "trang_thai" => $cached['trang_thai'],
                    "from_cache" => true
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
    } catch (Exception $e) {
        // Bảng chưa tạo hoặc lỗi truy vấn -> bỏ qua, tra cứu như bình thường
    }
}

// ==========================================================
// 3. CHƯA CÓ CACHE (HOẶC CACHE ĐÃ CŨ) -> GỌI NGUỒN NGOÀI
// ==========================================================
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
    // Nếu nguồn ngoài lỗi nhưng vẫn có cache cũ (dù đã quá hạn), trả tạm cache cũ còn hơn không có
    if ($pdo && isset($cached) && $cached) {
        echo json_encode([
            "success" => true,
            "ten_cong_ty" => $cached['ten_cong_ty'],
            "nguoi_dai_dien" => $cached['nguoi_dai_dien'],
            "dia_chi" => $cached['dia_chi'],
            "ten_giao_dich" => $cached['ten_giao_dich'],
            "co_quan_thue" => $cached['co_quan_thue'],
            "trang_thai" => $cached['trang_thai'],
            "from_cache" => true,
            "cache_outdated" => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(["success" => false, "message" => "Không tải được dữ liệu từ trang nguồn"], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. CHIẾN THUẬT QUÉT XPATH CHUẨN XÁC THEO CÁC MỐC CHỮ MỚI
$tenCongTy = "Chưa cập nhật";
$nguoiDaiDien = "Chưa cập nhật";
$diaChi = "Chưa cập nhật";
$tenGiaoDich = "Chưa cập nhật";
$coQuanThue = "Chưa cập nhật";
$canBoThue = "Chưa cập nhật";
$trangThai = "Chưa cập nhật";
$ppTinhThue = "Chưa cập nhật";
$nganhNghe = "Chưa cập nhật";

// Khởi tạo DOM Document đọc mã UTF-8 sạch lỗi font
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);

// 4.1. Bóc Tên công ty từ thẻ <title>
if (preg_match('/<title>(.*?)<\/title>/iu', $html, $matches)) {
    $titleText = trim($matches[1]);

    // BƯỚC CẮT ĐUÔI: Loại bỏ phần sau dấu gạch đứng "|" trước
    $cleanText = explode('|', $titleText)[0];

    // BƯỚC CẮT ĐẦU: Xóa MST và dấu gạch ngang ở đầu chuỗi
    $tenCongTy = preg_replace('/^[0-9\s-]+-\s+/iu', '', $cleanText);

    // Làm sạch khoảng trắng thừa 2 đầu còn sót lại
    $tenCongTy = trim($tenCongTy);
}

// 4.2. Dùng XPath bốc "Người đại diện"
$nguoiDaiDienNode = $xpath->query("//td[contains(text(), 'Người đại diện') or contains(text(), 'Đại diện')]/following-sibling::td[1]");
if ($nguoiDaiDienNode->length > 0) {
    $nguoiDaiDien = trim($nguoiDaiDienNode->item(0)->nodeValue);
}

// 4.3. Dùng XPath bốc "Địa chỉ trụ sở"
$diaChiNode = $xpath->query("//td[contains(text(), 'Địa chỉ') or contains(text(), 'Trụ sở')]/following-sibling::td[1]/div[1]");
if ($diaChiNode->length > 0) {
    $rawDiaChi = $diaChiNode->item(0)->nodeValue;
    if (str_contains($rawDiaChi, '- Căn cứ')) {
        $diaChi = trim(explode('- Căn cứ', $rawDiaChi)[0]);
    } else {
        $diaChi = trim($rawDiaChi);
    }
}

// 4.4. Dùng XPath bốc "Tên giao dịch" (Tên tiếng Anh)
$tenGiaoDichNode = $xpath->query("//td[contains(text(), 'Tên giao dịch')]/following-sibling::td[1]");
if ($tenGiaoDichNode->length > 0) {
    $tenGiaoDich = trim($tenGiaoDichNode->item(0)->nodeValue);
}

// 4.5. Dùng XPath bốc "Cơ quan thuế quản lý"
$coQuanThueNode = $xpath->query("//td[contains(text(), 'Cơ quan thuế')]/following-sibling::td[1]");
if ($coQuanThueNode->length > 0) {
    $coQuanThue = trim($coQuanThueNode->item(0)->nodeValue);
}

// 4.6. Dùng XPath bốc "Trạng thái hoạt động"
$trangThaiNode = $xpath->query("//td[contains(text(), 'Trạng thái')]/following-sibling::td[1]");
if ($trangThaiNode->length > 0) {
    $trangThai = trim($trangThaiNode->item(0)->nodeValue);
}

$canBoThueNode = $xpath->query("//td[contains(text(), 'Cán bộ')]/following-sibling::td[1]"); 
if ($canBoThueNode->length > 0) {
    $canBoThue = trim($canBoThueNode->item(0)->nodeValue);
}

$ppTinhthueNode = $xpath->query("//td[contains(text(), 'PP tính thuế')]/following-sibling::td[1]"); 
if ($ppTinhthueNode->length > 0) {
    $ppTinhThue = trim($ppTinhthueNode->item(0)->nodeValue);
}

$nganhNgheNode = $xpath->query("//td[contains(text(), 'Ngành nghề chính')]/following-sibling::td[1]/span[1]");
if ($nganhNgheNode->length > 0) {
    $nganhNghe = trim($nganhNgheNode->item(0)->nodeValue);
}

//  SỬA LẠI ĐOẠN NÀY:
// 4.7. Dùng XPath bốc "Cán bộ thuế" 
// $canBoThueNode = $xpath->query("//td[contains(text(), 'Cán bộ')]/following-sibling::td[1]"); 
// if ($canBoThueNode->length > 0) {
//     $canBoThue = trim($canBoThueNode->item(0)->nodeValue);
// } else {
//     $canBoThue = "Chưa cập nhật";
// }



// Hàm dọn dẹp các ký tự khoảng trắng hoặc định dạng dư thừa ở đầu/cuối chuỗi
function clean_output($str) {
    $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    $str = str_replace(['\"', '\\'], ['', ''], $str);
    return trim($str, " :-,");
}

// Hàm dọn dẹp các ký tự khoảng trắng hoặc định dạng dư thừa ở đầu/cuối chuỗi
// function clean_output($str) {
//     // Nếu vô tình truyền vào một đối tượng DOMNodeList, lấy text của phần tử đầu tiên
//     if ($str instanceof DOMNodeList) {
//         $str = ($str->length > 0) ? $str->item(0)->nodeValue : '';
//     }

//     $str = html_entity_decode($str, ENT_QUOTES, 'UTF-8');
//     $str = str_replace(['\"', '\\'], ['', ''], $str);
//     return trim($str, " :-,");
// }

$tenCongTy = clean_output($tenCongTy);
$nguoiDaiDien = clean_output($nguoiDaiDien);
$diaChi = clean_output($diaChi);
$tenGiaoDich = clean_output($tenGiaoDich);
$coQuanThue = clean_output($coQuanThue);
$canBoThue = clean_output($canBoThue);
$ppTinhThue = clean_output($ppTinhThue);
$nganhNghe = clean_output($nganhNghe);
$trangThai = clean_output($trangThai);


$tenCongTy = !empty($tenCongTy) ? $tenCongTy : "Chưa cập nhật";
$nguoiDaiDien = !empty($nguoiDaiDien) ? $nguoiDaiDien : "Chưa cập nhật";
$diaChi = !empty($diaChi) ? $diaChi : "Chưa cập nhật";
$canBoThue = !empty($canBoThue) ? $canBoThue : "Chưa cập nhật";
$tenGiaoDich = !empty($tenGiaoDich) ? $tenGiaoDich : "Chưa cập nhật";
$coQuanThue = !empty($coQuanThue) ? $coQuanThue : "Chưa cập nhật";
$ppTinhThue = !empty($ppTinhThue) ? $ppTinhThue : "Chưa cập nhật";
$nganhNghe = !empty($nganhNghe) ? $nganhNghe : "Chưa cập nhật";
$trangThai = !empty($trangThai) ? $trangThai : "Chưa cập nhật";
    

// ==========================================================
// 5. LƯU/CẬP NHẬT VÀO DATABASE ĐỂ LẦN SAU TRA CỨU NHANH HƠN
// ==========================================================
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO mst_cache (mst, ten_cong_ty, nguoi_dai_dien, dia_chi, ten_giao_dich, co_quan_thue, trang_thai, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                ten_cong_ty = VALUES(ten_cong_ty),
                nguoi_dai_dien = VALUES(nguoi_dai_dien),
                dia_chi = VALUES(dia_chi),
                ten_giao_dich = VALUES(ten_giao_dich),
                co_quan_thue = VALUES(co_quan_thue),
                trang_thai = VALUES(trang_thai),
                updated_at = NOW()
        ");
        $stmt->execute([$mst, $tenCongTy, $nguoiDaiDien, $diaChi, $tenGiaoDich, $coQuanThue, $trangThai]);
    } catch (Exception $e) {
    die($e->getMessage());
}
}

// 6. Trả kết quả JSON đầy đủ các trường về cho Frontend
echo json_encode([
    "success" => true,
    "ten_cong_ty" => $tenCongTy,
    "nguoi_dai_dien" => $nguoiDaiDien,
    "dia_chi" => $diaChi,
    "ten_giao_dich" => $tenGiaoDich,
    "can_bo_thue" => $canBoThue,
    "co_quan_thue" => $coQuanThue,
    "pp_tinh_thue" => $ppTinhThue,
    "nganh_nghe" => $nganhNghe,
    "trang_thai" => $trangThai,
    "from_cache" => false
], JSON_UNESCAPED_UNICODE);
exit;