import 'package:eshop_plus/core/localization/labelKeys.dart';

const awaitingStatusType = 'awaiting';
const receivedStatusType = 'received';
const processedStatusType = 'processed';
const shippedStatusType = 'shipped';
const deliveredStatusType = 'delivered';
const cancelledStatusType = 'cancelled';
const returnedStatusType = 'returned';
const returnRequestPendingStatusType = 'return_request_pending';
const returnRequestApprovedStatusType = 'return_request_approved';
const returnRequestDeclineStatusType = 'return_request_decline';

String comboType = 'combo';
String regularType = 'regular';
String variableLevelStockMgmtType = 'variable_level';
String addStockUpdateType = 'add';
String subtractStockUpdateType = 'subtract';
String simpleOrderType = 'simple';
String digitalOrderType = 'digital';
String comboOrderType = "combo_order";
String imageMediaType = 'image';
String videoMediaType = 'video';

const String defaultTransactionType = 'transaction';
String walletTransactionType = 'wallet';
String creditType = 'credit';
String debitType = 'debit';
String successTxnStatus = 'Transaction successful';
String failureTxnStatus = 'Transaction failed';
String pendingTxnStatus = 'Transaction pending';
String cancelledTxnStatus = 'Transaction cancelled';
String succeededStatus = 'succeeded';
String pendingStatus = 'pending';
String capturedStatus = 'captured';

Map<String, String> orderStatusTypes = {
  awaitingStatusType: awaitingStatusType,
  receivedStatusType: receivedKey,
  processedStatusType: processedKey,
  shippedStatusType: onTheWayKey,
  deliveredStatusType: deliveredKey,
  cancelledStatusType: cancelledKey,
  returnedStatusType: returnedKey,
};

const descendingOrder = 'desc';
const ascendingOrder = 'asc';
const int maxSearchHistory = 5;

const String selfHostedType = 'self_hosted';
const String youtubeVideoType = 'youtube';

const String keyNotifications = "notificationsKey";

const String englishLangCode = 'en';

//notification types
const String messageNotificationType = 'message';
const String walletNotificationType = 'wallet';
const String cartNotificationType = 'cart';
const String orderNotificationType = 'order';
const String defaultNotificationType = 'default';
const String notificationUrlNotificationType = 'notification_url';
const String productsNotificationType = 'products';
const String categoriesNotificationType = 'categories';
const String ticketStatusNotificationType = 'ticket_status';
const String ticketMessageNotificationType = 'ticket_message';

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
  codKey: "COD",
  bankTransferKey: "Bank Transfer",
};
