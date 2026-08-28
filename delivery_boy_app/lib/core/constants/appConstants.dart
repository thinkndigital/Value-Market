import 'package:eshoppro_deliveryboy/core/localization/labelKeys.dart';

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
const returnRequestPendingStatusType = 'return_request_pending';
const returnRequestApproveStatusType = 'return_request_approved';
const returnRequestRejectedStatusType = 'return_request_rejected';
const returnPickedupStatusType = 'return_pickedup';

String simpleOrderType = 'simple';
String digitalOrderType = 'digital';
Map<String, String> orderFilterTypes = {
  simpleOrderType: simpleKey,
  digitalOrderType: digitalKey
};
Map<String, String> orderStatusTypes = {
  receivedStatusType: receivedKey,
  processedStatusType: processedKey,
  shippedStatusType: shippedKey,
  deliveredStatusType: deliveredKey,
  cancelledStatusType: cancelledKey,
  returnedStatusType: returnedKey
};
Map<String, String> orderStatusFilter = {"": allKey, ...orderStatusTypes};

String creditType = 'credit';
String debitType = 'debit';

String riderCashType = 'delivery_boy_cash'; // (delivery boy collected)
String cashCollectionType = 'delivery_boy_cash_collection'; // (admin collected)

String percentagePerOrder = 'percentage_per_order_item';
String fixedAmountPerOrder = 'fixed_amount_per_order_item';

Map<String, String> bonusType = {
  percentagePerOrder: percentagePerOrderKey,
  fixedAmountPerOrder: fixedAmountPerOrderKey
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
// Map for display names
const Map<String, String> paymentGatewayDisplayNames = {
  paypalKey: "PayPal",
  phonepeKey: "PhonePe",
  razorpayKey: "Razorpay",
  paystackKey: "Paystack",
  stripeKey: "Stripe",
  cashOnDeliveryKey: "Cash on Delivery",
  codKey: "cod",
  bankTransferKey: "Bank Transfer",
};
