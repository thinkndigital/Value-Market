import 'package:value_market_customer/commons/models/systemSettings.dart';

class PaymentMethod {
  int? phonepeMethod;
  String? phonepeMode;
  String? phonepeMarchantId;
  String? phonepeSaltIndex;
  String? phonepeSaltKey;
  int? paypalMethod;
  String? paypalMode;
  String? paypalBusinessEmail;
  String? paypalClientId;
  String? currencyCode;
  int? razorpayMethod;
  String? razorpayMode;
  String? razorpayKeyId;
  String? razorpaySecretKey;
  String? razorpayWebhookSecretKey;
  int? midtransMethod;
  String? midtransPaymentMode;
  String? midtransClientKey;
  String? midtransServerKey;
  String? midtransMerchantId;
  int? paystackMethod;
  String? paystackKeyId;
  String? paystackSecretKey;
  int? stripeMethod;
  String? stripePaymentMode;
  String? stripePublishableKey;
  String? stripeSecretKey;
  String? stripeWebhookSecretKey;
  String? stripeCurrencyCode;
  int? flutterwaveMethod;
  String? flutterwavePublicKey;
  String? flutterwaveSecretKey;
  String? flutterwaveEncryptionKey;
  String? flutterwaveCurrencyCode;
  String? flutterwaveWebhookSecretKey;
  int? fatoorahMethod;
  String? myfatoorahToken;
  String? myfatoorahPaymentMode;
  String? myfatoorahLanguage;
  String? myfatoorahWebhookUrl;
  String? myfatoorahCountry;
  String? myfatoorahSuccessUrl;
  String? myfatoorahErrorUrl;
  String? myfatoorahSecretKey;

  String? notes;
  int? codMethod;
  int? directBankTransferMethod;
  String? accountName;
  String? accountNumber;
  String? bankName;
  String? bankCode;
  CurrencySetting? currencySetting;

  PaymentMethod(
      {this.phonepeMethod,
      this.phonepeMode,
      this.phonepeMarchantId,
      this.phonepeSaltIndex,
      this.phonepeSaltKey,
      this.paypalMethod,
      this.paypalMode,
      this.paypalBusinessEmail,
      this.paypalClientId,
      this.currencyCode,
      this.razorpayMethod,
      this.razorpayMode,
      this.razorpayKeyId,
      this.razorpaySecretKey,
      this.razorpayWebhookSecretKey,
      this.midtransMethod,
      this.midtransPaymentMode,
      this.midtransClientKey,
      this.midtransServerKey,
      this.midtransMerchantId,
      this.paystackMethod,
      this.paystackKeyId,
      this.paystackSecretKey,
      this.stripeMethod,
      this.stripePaymentMode,
      this.stripePublishableKey,
      this.stripeSecretKey,
      this.stripeWebhookSecretKey,
      this.stripeCurrencyCode,
      this.flutterwaveMethod,
      this.flutterwavePublicKey,
      this.flutterwaveSecretKey,
      this.flutterwaveEncryptionKey,
      this.flutterwaveCurrencyCode,
      this.flutterwaveWebhookSecretKey,
      this.fatoorahMethod,
      this.myfatoorahToken,
      this.myfatoorahPaymentMode,
      this.myfatoorahLanguage,
      this.myfatoorahWebhookUrl,
      this.myfatoorahCountry,
      this.myfatoorahSuccessUrl,
      this.myfatoorahErrorUrl,
      this.myfatoorahSecretKey,
      this.directBankTransferMethod,
      this.accountName,
      this.accountNumber,
      this.bankName,
      this.bankCode,
      this.notes,
      this.codMethod,
      this.currencySetting});

  PaymentMethod.fromJson(Map<String, dynamic> json) {
    phonepeMethod = json['phonepe_method'];
    phonepeMode = json['phonepe_mode'];
    phonepeMarchantId = json['phonepe_marchant_id'];
    phonepeSaltIndex = json['phonepe_salt_index'];
    phonepeSaltKey = json['phonepe_salt_key'];
    paypalMethod = json['paypal_method'];
    paypalMode = json['paypal_mode'];
    paypalBusinessEmail = json['paypal_business_email'];
    paypalClientId = json['paypal_client_id'];
    currencyCode = json['currency_code'];
    razorpayMethod = json['razorpay_method'];
    razorpayMode = json['razorpay_mode'];
    razorpayKeyId = json['razorpay_key_id'];
    razorpaySecretKey = json['razorpay_secret_key'];
    razorpayWebhookSecretKey = json['razorpay_webhook_secret_key'];
    midtransMethod = json['midtrans_method'];
    midtransPaymentMode = json['midtrans_payment_mode'];
    midtransClientKey = json['midtrans_client_key'];
    midtransServerKey = json['midtrans_server_key'];
    midtransMerchantId = json['midtrans_merchant_id'];
    paystackMethod = json['paystack_method'];
    paystackKeyId = json['paystack_key_id'];
    paystackSecretKey = json['paystack_secret_key'];
    stripeMethod = json['stripe_method'];
    stripePaymentMode = json['stripe_payment_mode'];
    stripePublishableKey = json['stripe_publishable_key'];
    stripeSecretKey = json['stripe_secret_key'];
    stripeWebhookSecretKey = json['stripe_webhook_secret_key'];
    stripeCurrencyCode = json['stripe_currency_code'];
    flutterwaveMethod = json['flutterwave_method'];
    flutterwavePublicKey = json['flutterwave_public_key'];
    flutterwaveSecretKey = json['flutterwave_secret_key'];
    flutterwaveEncryptionKey = json['flutterwave_encryption_key'];
    flutterwaveCurrencyCode = json['flutterwave_currency_code'];
    flutterwaveWebhookSecretKey = json['flutterwave_webhook_secret_key'];
    fatoorahMethod = json['fatoorah_method'];
    myfatoorahToken = json['myfatoorah_token'];
    myfatoorahPaymentMode = json['myfatoorah_payment_mode'];
    myfatoorahLanguage = json['myfatoorah_language'];
    myfatoorahWebhookUrl = json['myfatoorah__webhook_url'];
    myfatoorahCountry = json['myfatoorah_country'];
    myfatoorahSuccessUrl = json['myfatoorah__successUrl'];
    myfatoorahErrorUrl = json['myfatoorah__errorUrl'];
    myfatoorahSecretKey = json['myfatoorah__secret_key'];
    directBankTransferMethod = json['direct_bank_transfer_method'];
    accountName = json['account_name'];
    accountNumber = json['account_number'];
    bankName = json['bank_name'];
    bankCode = json['bank_code'];
    notes = json['notes'];
    codMethod = json['cod_method'] is int
        ? json['cod_method']
        : int.tryParse(json['cod_method']?.toString() ?? '');
    directBankTransferMethod = json['direct_bank_transfer_method'] is int
        ? json['direct_bank_transfer_method']
        : int.tryParse(json['direct_bank_transfer_method']?.toString() ?? '');
    accountName = json['account_name'] as String?;
    accountNumber = json['account_number'] as String?;
    bankName = json['bank_name'] as String?;
    bankCode = json['bank_code'] as String?;
    currencySetting = json['currency_setting'] != null
        ? CurrencySetting.fromJson(json['currency_setting'])
        : null;
  }
}

class PaymentModel {
  bool? isSelected;
  final String? image;
  final String? name;

  String? phonepeMode;
  String? phonepeMarchantId;
  String? phonepeSaltIndex;
  String? phonepeSaltKey;

  String? paypalMode;
  String? paypalBusinessEmail;
  String? paypalClientId;
  String? currencyCode;

  String? razorpayMode;
  String? razorpayKeyId;
  String? razorpaySecretKey;
  String? razorpayWebhookSecretKey;

  String? paystackKeyId;
  String? paystackSecretKey;

  String? stripePaymentMode;
  String? stripePublishableKey;
  String? stripeSecretKey;
  String? stripeWebhookSecretKey;
  String? stripeCurrencyCode;

  String? accountName;
  String? accountNumber;
  String? bankName;
  String? bankCode;
  String? notes;
  PaymentModel(
      {this.isSelected,
      this.name,
      this.image,
      this.phonepeMode,
      this.phonepeMarchantId,
      this.phonepeSaltIndex,
      this.phonepeSaltKey,
      this.paypalMode,
      this.paypalBusinessEmail,
      this.paypalClientId,
      this.currencyCode,
      this.razorpayMode,
      this.razorpayKeyId,
      this.razorpaySecretKey,
      this.razorpayWebhookSecretKey,
      this.paystackKeyId,
      this.paystackSecretKey,
      this.stripePaymentMode,
      this.stripePublishableKey,
      this.stripeSecretKey,
      this.stripeWebhookSecretKey,
      this.stripeCurrencyCode,
      this.accountName,
      this.accountNumber,
      this.bankCode,
      this.bankName,
      this.notes});
  PaymentModel copyWith(
      {bool? isSelected,
      String? image,
      String? name,
      String? phonepeMode,
      String? phonepeMarchantId,
      String? phonepeSaltIndex,
      String? phonepeSaltKey,
      String? paypalMode,
      String? paypalBusinessEmail,
      String? paypalClientId,
      String? currencyCode,
      String? razorpayMode,
      String? razorpayKeyId,
      String? razorpaySecretKey,
      String? razorpayWebhookSecretKey,
      String? paystackKeyId,
      String? paystackSecretKey,
      String? stripePaymentMode,
      String? stripePublishableKey,
      String? stripeSecretKey,
      String? stripeWebhookSecretKey,
      String? stripeCurrencyCode,
      String? accountName,
      String? accountNumber,
      String? bankName,
      String? bankCode,
      String? notes}) {
    return PaymentModel(
        isSelected: isSelected ?? this.isSelected,
        image: image ?? this.image,
        name: name ?? this.name,
        phonepeMode: phonepeMode ?? this.phonepeMode,
        phonepeMarchantId: phonepeMarchantId ?? this.phonepeMarchantId,
        phonepeSaltIndex: phonepeSaltIndex ?? this.phonepeSaltIndex,
        phonepeSaltKey: phonepeSaltKey ?? this.phonepeSaltKey,
        paypalMode: paypalMode ?? this.paypalMode,
        paypalBusinessEmail: paypalBusinessEmail ?? this.paypalBusinessEmail,
        paypalClientId: paypalClientId ?? this.paypalClientId,
        currencyCode: currencyCode ?? this.currencyCode,
        razorpayMode: razorpayMode ?? this.razorpayMode,
        razorpayKeyId: razorpayKeyId ?? this.razorpayKeyId,
        razorpaySecretKey: razorpaySecretKey ?? this.razorpaySecretKey,
        razorpayWebhookSecretKey:
            razorpayWebhookSecretKey ?? this.razorpayWebhookSecretKey,
        paystackKeyId: paystackKeyId ?? this.paystackKeyId,
        paystackSecretKey: paystackSecretKey ?? this.paystackSecretKey,
        stripePaymentMode: stripePaymentMode ?? this.stripePaymentMode,
        stripePublishableKey: stripePublishableKey ?? this.stripePublishableKey,
        stripeSecretKey: stripeSecretKey ?? this.stripeSecretKey,
        stripeWebhookSecretKey:
            stripeWebhookSecretKey ?? this.stripeWebhookSecretKey,
        stripeCurrencyCode: stripeCurrencyCode ?? this.stripeCurrencyCode,
        accountName: accountName ?? this.accountName,
        accountNumber: accountNumber ?? this.accountNumber,
        bankCode: bankCode ?? this.bankCode,
        bankName: bankName ?? this.bankName,
        notes: notes ?? this.notes);
  }
}
