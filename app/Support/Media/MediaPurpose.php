<?php

namespace App\Support\Media;

enum MediaPurpose: string
{
    case UserAvatar = 'user_avatar';
    case EmployeeAvatar = 'employee_avatar';
    case SellerAvatar = 'seller_avatar';
    case StoreLogo = 'store_logo';
    case StoreBanner = 'store_banner';
    case ProductCover = 'product_cover';
    case ProductImage = 'product_image';
    case ProductVariant = 'product_variant';
    case ArticleCover = 'article_cover';
    case ArticleImage = 'article_image';
    case ArticleVideoThumbnail = 'article_video_thumbnail';
}
