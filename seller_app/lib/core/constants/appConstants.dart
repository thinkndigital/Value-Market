import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:intl/intl.dart';

const String englishLangCode = 'en';

DateFormat displayDateFormat = DateFormat('dd-MM-yyyy');
DateFormat apiDateTimeFormat = DateFormat('yyyy-MM-dd HH:mm:ss');

String simpleProductType = 'simple_product';
String variableProductType = 'variable_product';
String digitalProductType = 'digital_product';
String physicalProductType = 'physical_product';
const String comboProductType = "combo-product";
const String regularProductType = "regular_product";

const awaitingStatusType = 'awaiting';
const receivedStatusType = 'received';
const processedStatusType = 'processed';
const shippedStatusType = 'shipped';
const deliveredStatusType = 'delivered';
const cancelledStatusType = 'cancelled';
const returnedStatusType = 'returned';
const draftStatusType = 'draft';
const returnedRequestPendingType = 'return_request_pending';
const returnedRequestApprovedType = 'return_request_approved';
const returnRequestDeclineStatusType = 'return_request_decline';

String productLevelStockMagmtType = 'product_level';
String variableLevelStockMgmtType = 'variable_level';
//1 - product level , 2 - variable level
String productLevelStockMgmtTypeNo = '1';
String variableLevelStockMgmtTypeNo = '2';

String zipcodeWiseDeliverability = "zipcode_wise_deliverability";
String cityWiseDeliverability = "city_wise_delivery_charge";

String mediaTypeImage = "image";
String mediaTypeVideo = "video";
String mediaTypeAudio = "audio";

String addStockUpdateType = 'add';
String subtractStockUpdateType = 'subtract';
String simpleOrderType = 'simple';
String digitalOrderType = 'digital';

Map<String, String> productTypes = {
  simpleProductType: simpleProductKey,
  variableProductType: variableProductKey,
};
Map<String, String> mainProductTypes = {
  physicalProductType: physicalProductKey,
  digitalProductType: digitalProductKey,
};
Map<String, String> allProductTypes = {
  simpleProductType: simpleProductKey,
  variableProductType: variableProductKey,
  digitalProductType: digitalProductKey,
  comboProductType: comboProductsKey,
};
Map<String, String> indicatorTypes = {
  '0': noneKey,
  '1': vegKey,
  '2': nonVegKey
};
isSelectZipCode(String deliverabletype) {
  //NULL: if deliverable_type = 0 or 1
  return deliverabletype != "0" && deliverabletype != "1";
}

Map<String, String> productDeliverableTypes = {
  '0': noneKey,
  '1': allKey,
  '2': specificZonesKey,
};
Map<String, String> sellerDeliverableTypes = {
  '1': allKey,
  '2': specificZonesKey,
};
Map<String, String> stockStatusTypes = {
  '1': inStockKey,
  '0': outOfStockKey,
};
Map<String, String> productStatusTypes = {
  '1': activeKey,
  '0': deactivedKey,
};
Map<String, String> cancelableStatusTypes = {
  receivedStatusType: receivedKey,
  processedStatusType: processedKey,
  shippedStatusType: shippedKey
};
Map<String, String> stockMgmtTypes = {
  productLevelStockMagmtType: productLevelKey,
  variableLevelStockMgmtType: variableLevelKey
};

Map<String, String> stockMgmtTypesBackend = {
  productLevelStockMgmtTypeNo: productLevelStockMagmtType,
  variableLevelStockMgmtTypeNo: variableLevelStockMgmtType
};

Map<String, String> stockUpdateTypes = {
  addStockUpdateType: addKey,
  subtractStockUpdateType: subtractKey
};

Map<String, String> orderFilterTypes = {
  simpleOrderType: simpleKey,
  digitalOrderType: digitalKey
};

String addLinkType = 'add_link';
String vimeoType = 'vimeo';
String youtubeType = 'youtube';
String selfHostedType = 'self_hosted';

Map<String, String> videoTypes = {
  '': noneKey,
  vimeoType: vimeoKey,
  youtubeType: youtubeKey,
  selfHostedType: selfHostedKey
};

Map<String, String> downloadLinkTypes = {
  "": noneKey,
  addLinkType: addLinkKey,
  selfHostedType: selfHostedKey
};

Map<String, String> orderStatusTypes = {
  receivedStatusType: receivedKey,
  processedStatusType: processedKey,  
  shippedStatusType: shippedKey,
  deliveredStatusType: deliveredKey,
  cancelledStatusType: cancelledKey,
  returnedStatusType: returnedKey
};
String creditType = 'credit';
String debitType = 'debit';

Map<String, String> sellerOverviewStatusTypes = {
  'monthly': monthlyKey,
  'weekly': weeklyKey,
  'yearly': yearlyKey
};
List<String> imagetypelist = [
  "jpg",
  "jpeg",
  "png",
  "gif",
  "webp",
  "tiff",
  "psd",
  "raw",
  "bmp",
  "heif",
  "indd",
  "jpeg 2000",
  "jfif",
  "exif"
];

const String paypalKey = "paypal";
const String phonepeKey = "phonepe";
const String razorpayKey = "razorpay";
const String paystackKey = "paystack";
const String stripeKey = "stripe";
const String cashOnDeliveryKey = "cash_on_delivery";
const String codKey = "cod";
const String bankTransferKey = "bank_transfer";
const String walletKey = "wallet";

// Map for display names
const Map<String, String> paymentGatewayDisplayNames = {
  paypalKey: "PayPal",
  phonepeKey: "PhonePe",
  razorpayKey: "Razorpay",
  paystackKey: "Paystack",
  stripeKey: "Stripe",
  cashOnDeliveryKey: "Cash on Delivery",
  codKey: "COD",
  bankTransferKey: "Bank Transfer",
  walletKey: "Wallet"
};
