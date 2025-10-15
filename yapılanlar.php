<?php

return [
    'veritabani_ve_konfigurasyon' => [
        'database_yonetimi' => 'PDO baglantisi, soket/port desteği ve tablo kolon kontrolü ile paylaşılan bağlantı yönetimi.',
        'ayar_cache' => 'Sistem ayarlarını veritabanından okuyup bellek içi önbelleğe alan anahtar-değer altyapısı.',
        'ozellik_bayraklari' => 'Varsayılanlarla birleşen dinamik Feature Toggle kontrolleri.',
    ],
    'ceviri_ve_arayuz' => [
        'dil_yonetimi' => 'Oturum tabanlı dil seçimi, anahtar çevirileri ve çıktı filtreleme desteği.',
        'anasayfa_slider' => 'Yönetim paneli üzerinden JSON ayarları ile özelleşen varsayılan slider/kategori içeriği.',
    ],
    'kullanici_ve_guvenlik' => [
        'rol_sistemi' => 'Super admin, admin, finance, support, content ve customer rollerine sahip yetki matrisi.',
        'kimlik_dogrulama' => 'Parola karması, oturum kaydı, parola sıfırlama tokeni ve cihaz/IP günlükleme.',
        'bildirimler' => 'Global/kişisel bildirimler için tablo oluşturma, okuma durum takibi ve toplu işaretleme.',
        'audit_log' => 'Yönetim aksiyonlarını IP bilgisi ile loglayan denetim kayıtları.',
    ],
    'ticaret_islemleri' => [
        'sepet_yonetimi' => 'Oturum tabanlı sepet, para birimi dönüştürme, satır toplamları ve eylem logları.',
        'para_birimi' => 'Döviz kuru önbelleği, harici API yedekleri, formatlama ve komisyonlu fiyat hesapları.',
        'raporlama' => 'Paket/ürün siparişleri ile bakiye hareketlerine göre aylık ve tarih aralıklı özetler.',
        'paket_teslimi' => 'Siparişten kullanıcı açma/güncelleme, bakiye kredi kaydı ve e-posta/Telegram bildirimi.',
    ],
    'odeme_ve_bakiye' => [
        'odeme_gecitleri' => 'Cryptomus ve Heleket entegrasyonları, imza doğrulaması ve dinamik gateway listesi.',
        'telegram_bildirimi' => 'Telegram bot API ile hızlı operasyon bildirimleri.',
        'mailer' => 'SMTP transportu ve mail() geridönüşüne sahip dinamik kimlik bilgilerinin kullanımı.',
        'api_tokenleri' => 'Müşteri bazlı token üretimi, webhook yönetimi ve giden istek imzalama.',
    ],
    'entegrasyonlar' => [
        'woocommerce_import_export' => 'CSV başlık haritalama, kategori yolu oluşturma, SKU bazlı güncelleme ve dışa aktarım.',
        'woocommerce_api' => 'Sipariş oluşturma/bakiye düşümü ve ürün-kategori servisleri sağlayan REST uçları.',
        'playsel_servisi' => 'Playsel API kimliği doğrulama, ürün senkronizasyonu ve kategori otomasyonu.',
    ],
    'yapay_zeka_cozumleri' => [
        'icerik_otomasyonu' => 'Ürün açıklaması, yorum ve blog üretimi; zamanlama, hata raporlama ve homepage yenilemesi.',
        'gorsel_uretimi' => 'DALL-E tabanlı ürün/blog görseli üretimi, maliyet tahmini ve dosya depolama.',
        'openai_istemcisi' => 'Metin/görsel istekleri, çok parçalı yüklemeler ve faturalandırma bakiyesi sorgusu.',
    ],
];
