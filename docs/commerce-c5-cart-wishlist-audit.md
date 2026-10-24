# Commerce C5 Cart & Wishlist Audit

## Kết luận source-of-truth

- C4 đã có runtime thật cho `buyer_profiles`, `seller_profiles`, `stores`, `products`, `product_variants`, `inventory_locations`, `inventory_balances` và `media_assets`.
- Không có bảng, route, controller hoặc service Cart/Wishlist đang chạy. Các module scaffold cũ không được enable.
- Giá bán hiện hành nằm ở `product_variants.price_minor` (VND integer minor units). `products.base_price_minor` chỉ là giá đại diện.
- Availability hiện hành là tổng `on_hand - reserved` của Variant. Cart không thay đổi `reserved`; Checkout tương lai phải kiểm tra và reserve có thẩm quyền.
- Buyer privacy gắn với authenticated `users.id` có `buyer_profiles.status=active`, không gắn Employee/Company và không có admin override đọc Cart/Wishlist người khác.

## Quyết định schema

Migration additive tạo `carts`, `cart_items`, `wishlists`, `wishlist_items`. Unique constraints bảo đảm một active cart/user, một Variant/cart và một Product/wishlist. Cart/Wishlist là mutable consumer state; không giả lập Order history hay immutable checkout record.

## Rủi ro đã audit

- Multi-store cần group bằng Store trong read model, không tách nhiều Cart.
- Giá và trạng thái phải được re-evaluate mỗi lần đọc; snapshot chỉ dùng phát hiện đổi giá.
- Add cùng Variant cần transaction + row lock + unique constraint.
- Personalized pages không dùng cache dùng chung.
- Product list/search phải bounded pagination và batch media query.
