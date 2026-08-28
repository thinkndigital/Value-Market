class PaystackModel {
  final String authorizationUrl;
  final String accessCode;
  final String reference;
  final String callbackUrl;
//add fromJson constructor
  PaystackModel.fromJson(Map<String, dynamic> json)
      : authorizationUrl = json['authorization_url'],
        accessCode = json['access_code'],
        reference = json['reference'],
        callbackUrl = json['callback_url'];
  PaystackModel(
      {required this.authorizationUrl,
      required this.accessCode,
      required this.reference,
      required this.callbackUrl});
}
