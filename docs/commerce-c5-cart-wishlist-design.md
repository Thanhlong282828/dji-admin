# Commerce C5 Cart, Wishlist & Marketplace Design

## Boundary

`Product → Variant/SKU → Cart → future Checkout`. C5 không tạo Address, Shipping, Voucher, Payment, Order hoặc stock reservation.

## Cart

`CartService` là canonical write/read boundary. Add/update kiểm tra Product, Variant, Store, Seller và current availability trong transaction. Một Variant chỉ có một row; add lại cộng quantity. Read model group theo Store, giữ item stale để user thấy lý do và loại item invalid khỏi selected subtotal.

`CartPricingSummary`: subtotal = total trong C5; discount = 0; shipping excluded. Tiền luôn là integer minor units.

## Wishlist

Wishlist lưu Product-level. Like là idempotent insert; unlike là owner-scoped delete. Product nhiều active Variant phải quay lại Product detail để chọn; Product chỉ có một active Variant có thể add trực tiếp.

## Security

Mọi mutation resolve Cart/Wishlist từ authenticated user; không nhận user/cart owner từ client. Seller catalog ownership độc lập và không cấp quyền đọc Buyer state. Guest dùng Laravel intended URL để quay lại trang Product sau login.

## UX

Laravel server-rendered giữ fallback form. JavaScript chỉ progressive-enhance selection/quantity/toast/badge; không thêm SPA hoặc realtime. Public UI dùng white/blue, responsive, keyboard/focus/ARIA và không hiển thị rating, sold count, voucher hoặc promotion giả.
