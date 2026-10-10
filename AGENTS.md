# Quy ước phát triển toàn project

File này áp dụng cho toàn bộ repository. Mọi thay đổi mới hoặc chỉnh sửa chức năng hiện có phải tuân thủ các nguyên tắc dưới đây, trừ khi người dùng đưa ra yêu cầu cụ thể khác.

## Kiến trúc bắt buộc: Module + CQRS

- Mỗi nghiệp vụ phải nằm đúng module/bounded context sở hữu dữ liệu và hành vi đó; không đặt business logic rải rác trong Blade, controller hoặc module khác.
- Tách rõ luồng đọc và ghi theo CQRS:
  - Đọc dữ liệu qua Query/Query Handler/read model hoặc lớp truy vấn tương đương của module.
  - Ghi dữ liệu qua Command/Command Handler/application service và domain rules của module.
- Controller và Blade chỉ điều phối, render view model và gửi command/query; không truy vấn hoặc cập nhật dữ liệu nghiệp vụ trực tiếp.
- Luôn giữ đúng tenant/company/branch scope, quyền truy cập, ownership và các điều kiện nghiệp vụ hiện có.
- Không ghép dữ liệu của các bounded context khác nhau chỉ vì chúng trông giống nhau trên giao diện.

## Truy xuất nguồn dữ liệu và liên kết điều hướng

- Mọi KPI, con số tổng hợp, mã tham chiếu hoặc bản ghi hiển thị phải cho người dùng biết dữ liệu lấy từ đâu.
- Hàng số liệu đang nằm rời bên ngoài bố cục phải được chuyển thành KPI card phù hợp tone giao diện của project.
- Mỗi card phải có:
  - Nhãn rõ nghĩa.
  - Giá trị đã định dạng đúng.
  - Ngữ cảnh hoặc mô tả nguồn tính toán khi cần.
  - Link dẫn tới workspace, danh sách, chứng từ hoặc màn hình chi tiết canonical đã tạo ra số liệu đó.
- Trong table, các giá trị nhận diện được như reference, document number, employee, vendor, customer, order, invoice, journal, payroll run hoặc nguồn tổng hợp phải có link tới bản ghi/màn hình nguồn tương ứng khi người dùng có quyền truy cập.
- Link phải giữ nguyên `company_id`, branch scope và các filter cần thiết để người dùng không mất ngữ cảnh.
- Nếu việc xác định URL phụ thuộc loại nguồn hoặc nghiệp vụ, resolve URL trong query/read model/view model dùng chung; không lặp mapping phức tạp trong từng Blade.
- Link phải kiểm tra route, quyền và sự tồn tại của bản ghi. Khi không có quyền hoặc không thể resolve nguồn, vẫn hiển thị giá trị dạng text và trạng thái giải thích phù hợp, không tạo link hỏng.
- Nhãn hành động phải nói rõ đích đến, ví dụ `Mở đơn hàng nguồn →`, `Xem journal →`, `Mở workspace tồn kho →`; không dùng hành động mơ hồ khiến người dùng không biết sẽ đi đâu.

## Dữ liệu thật và luồng nghiệp vụ thật

- Không tạo demo row, synthetic row, clone, hard-code hoặc dữ liệu lấp chỗ chỉ để bảng trông có dữ liệu.
- Bảng chỉ được có dữ liệu từ luồng nghiệp vụ thật, bảng canonical thật và read model đúng nguồn.
- Khi bảng trống, trước tiên phải kiểm tra luồng upstream nào có trách nhiệm tạo dữ liệu và nối đúng luồng đó vào query hiện tại.
- Nếu thực tế chưa có bản ghi, hiển thị empty state trung thực gồm:
  - Lý do chưa có dữ liệu.
  - Luồng/module sở hữu nguồn dữ liệu.
  - CTA dẫn tới bước nghiệp vụ hợp lệ để tạo hoặc xử lý dữ liệu, nếu người dùng có quyền.
- Không coi `0` hoặc bảng trống là lỗi nếu đó là kết quả nghiệp vụ hợp lệ.
- Không seed, migration dữ liệu vận hành, sửa trực tiếp database hoặc tự tạo transaction để làm đầy màn hình nếu người dùng chưa yêu cầu rõ ràng.
- Khi nối dữ liệu giữa các module, phải giữ lineage/reference thật và trạng thái nghiệp vụ thật; không sao chép dữ liệu để tạo cảm giác đã tích hợp.

## UI/UX theo tone project

- Giữ ngôn ngữ thiết kế tối–vàng hiện có, typography, spacing, border, trạng thái và interaction nhất quán với các màn hình tốt trong project.
- Ưu tiên tái sử dụng component hiện có cho KPI card, source link, empty state, panel, table, status pill và action thay vì tạo markup/CSS riêng lẻ.
- Các con số tổng hợp dùng card/grid responsive; không để thành các dòng text rời, dính nhau hoặc nằm ngoài container.
- Table phải dễ quét, có hierarchy rõ, số tiền/cột số căn chỉnh hợp lý, trạng thái dễ nhận biết và link nguồn có affordance rõ ràng.
- Với danh sách dài, dùng layout card, ranked list hoặc table phù hợp; tránh trải text thô trên toàn chiều ngang màn hình.
- Bảo đảm hover, focus, keyboard navigation, độ tương phản, wrapping và responsive trên các kích thước màn hình phổ biến.
- Không đưa giao diện trắng/raw browser style hoặc component lệch tone vào project.

## Tiêu chí hoàn thành

- Kiểm tra các màn hình tương tự trong phạm vi thay đổi và áp dụng nhất quán quy ước card + source link + empty state.
- Xác minh route name, middleware, permission và ID từ read model trước khi sinh link.
- Xác minh dữ liệu hiển thị truy ngược được tới nguồn canonical và đúng company/branch.
- Chạy kiểm tra phù hợp với thay đổi: compile Blade, focused tests, accessibility/UI checks, Vite build khi sửa CSS/JS và `git diff --check`.
- Không hoàn thành công việc nếu còn link nguồn bị hỏng, dữ liệu giả để lấp bảng, business logic sai module hoặc vi phạm CQRS.

## Nguyên tắc sản phẩm đã chốt

> Hàng view số nằm ngoài phải thành card và gắn link tới nơi lấy dữ liệu. Thông tin trong table phải có link tới nguồn của nó. Phát triển theo Module + CQRS, giữ UI/UX đúng tone project. Bảng chưa có dữ liệu phải được nối vào luồng nghiệp vụ thực tế, không tạo dữ liệu cho có. Mọi thứ làm ra phải chỉ rõ và điều hướng được về nguồn.
