import 'package:value_market_delivery_boy/core/configs/appConfig.dart';

class ApiURL {
////Api end points////
static String register = "${databaseUrl}register";
static String login = "${databaseUrl}login";
static String verifyUser = "${databaseUrl}verify_user";
static String getUserDetails = "${databaseUrl}get_delivery_boy_details";
static String updateOrderItemStatus =
"${databaseUrl}update_order_item_status"; //
static String resetPassword = "${databaseUrl}reset_password";
static String updateFcm = "${databaseUrl}update_fcm";
static String getSettings = "${databaseUrl}get_settings";
static String getLanguages = "${databaseUrl}get_languages";
static String getLanguageLabels = "${databaseUrl}get_language_labels";
static String getOrders = "${databaseUrl}get_orders";
static String getTransactions = "${databaseUrl}get_wallet_transaction";
static String getWithdrawalRequest = "${databaseUrl}get_withdrawal_request";
static String sendWithdrawalRequest = "${databaseUrl}send_withdrawal_request";
static String deleteUserAccount = "${databaseUrl}delete_delivery_boy";
static String updateUser = "${databaseUrl}update_user";
static String deliveryBoyCashCollection =
"${databaseUrl}get_delivery_boy_cash_collection";
static String getZipcodes = "${databaseUrl}get_zipcodes";
static String getCities = "${databaseUrl}get_cities";
static String getNotifications = "${databaseUrl}get_notifications";
static String getProducts = "${databaseUrl}get_products";
static String getComboProducts = "${databaseUrl}get_combo_products";
static String addPickupLocation = "${databaseUrl}add_pickup_location";
static String getPickupLocation = "${databaseUrl}get_pickup_locations";
static String getSalesList = "${databaseUrl}get_sales_list";
static String getBrandList = "${databaseUrl}get_brand_list";
static String getCategoryList = "${databaseUrl}get_categories";
static String getCountryData = "${databaseUrl}get_countries_data";
static String getTaxes = "${databaseUrl}get_taxes";
static String getProductFaqs = "${databaseUrl}get_product_faqs";
static String addProductFaqs = "${databaseUrl}add_product_faqs";
static String editProductFaqs = "${databaseUrl}edit_product_faq";
static String deleteProduct = "${databaseUrl}delete_product";
static String deleteComboProduct = "${databaseUrl}delete_combo_product";
static String updateProductStatus = "${databaseUrl}update_product_status";
static String getProductRating = "${databaseUrl}get_product_rating";
static String getComboProductRating =
"${databaseUrl}get_combo_product_rating";
static String manageStock = "${databaseUrl}manage_stock";
static String chatifyAuthAPI = '$baseUrl/chatify/api/chat/auth';
static String chatifySendMessageApi = '$baseUrl/chatify/api/sendMessage';
static String chatifyFetchMessagesApi = '$baseUrl/chatify/api/fetchMessages';
static String chatifySearchApi = '$baseUrl/chatify/api/search';
static String chatifyGetContactsApi = '$baseUrl/chatify/api/getContacts';
static String chatifyMakeSeenApi = '$baseUrl/chatify/api/makeSeen';
static String getAttributes = "${databaseUrl}get_attributes";
static String getMedia = "${databaseUrl}get_media";
static String getTotalData = "${databaseUrl}get_total_data";
static String topSellingProducts = "${databaseUrl}top_selling_products";
static String mostSellingCategories = "${databaseUrl}most_selling_categories";
static String getOverviewStatistic = "${databaseUrl}get_overview_statistic";
static String uploadMedia = "${databaseUrl}upload_media";
static String addProducts = "${databaseUrl}add_products";
static String addSellerStore = "${databaseUrl}add_seller_store";
static String getZones = "${databaseUrl}get_zones";
static String updateReturnedOrderItemStatus =
"${databaseUrl}update_returned_order_item_status";
static String getReturnedOrderItems =
"${databaseUrl}get_returned_order_items";

///=====*** form keys=====
static String passwordApiKey = 'password';
static String mobileApiKey = 'mobile';
static String newApiKey = 'new';
static String oldApiKey = 'old';
static String fcmIdApiKey = 'fcm_id';
static String userIdApiKey = 'user_id';
static String offsetApiKey = 'offset';
static String limitApiKey = 'limit';
static String sortByApiKey = 'sort';
static String typeApiKey = 'type';
static String storeIdApiKey = 'store_id';
static String amountApiKey = 'amount';
static String paymentAddressApiKey = 'payment_address';
static String showOnlyStockProductApiKey = 'show_only_stock_product';
static String productIdApiKey = 'product_id';
static String searchApiKey = 'search';
static String questionApiKey = 'question';
static String answerApiKey = 'answer';
static String productTypeApiKey = 'product_type';
static String editIdApiKey = 'edit_id';
static String orderByApiKey = 'order';
static String topRatedProductApiKey = 'top_rated_product';
static String discountApiKey = 'discount';
static String flagApiKey = 'flag';
static String ratingApiKey = 'rating';
static String statusApiKey = 'status';
static String idApiKey = 'id';
static String fromIdApiKey = 'from_id';
static String toIdApiKey = 'to_id';
static String fileApiKey = 'file';
static String messageApiKey = 'message';
static String productVariantIdApiKey = 'product_variant_id';
static String quantityApiKey = 'quantity';
static String currentStockApiKey = 'current_stock';
static String categoryIdApiKey = 'category_id';
static String mobileNoApiKey = 'mobile_no';
static String isNotificationOnApiKey = 'is_notification_on';
static String languageCodeApiKey = 'language_code';


}
