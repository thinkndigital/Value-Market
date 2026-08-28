class UserDetails {
int? id;
int? roleId;
String? ipAddress;
String? username;
String? email;
String? mobile;
String? image;
double? balance;
String? activationSelector;
String? activationCode;
String? forgottenPasswordSelector;
String? forgottenPasswordCode;
String? forgottenPasswordTime;
String? rememberSelector;
String? rememberCode;
String? lastLogin;
String? active;
String? company;
String? address;
String? bonus;
String? cashReceived;
String? dob;
String? countryCode;
String? city;
String? area;
String? street;
String? pincode;
String? apikey;
String? referralCode;
String? friendsCode;
List<String>? fcmId;
String? latitude;
String? longitude;
String? type;
String? bonusType;
String? serviceableZipcodes;
String? serviceableCities;
String? backLicenceImage;
String? frontLicenceImage;
late int isNotificationOn;

UserDetails(
{id,
roleId,
bonusType,
ipAddress,
username,
email,
mobile,
image,
balance,
activationSelector,
activationCode,
forgottenPasswordSelector,
forgottenPasswordCode,
forgottenPasswordTime,
rememberSelector,
rememberCode,
lastLogin,
active,
company,
address,
bonus,
cashReceived,
dob,
countryCode,
city,
area,
street,
pincode,
apikey,
referralCode,
friendsCode,
fcmId,
latitude,
longitude,
type,
deliverableZones,
deliverableZoneIds,
backLicenceImage,
frontLicenceImage,
this.isNotificationOn = 1});
String? deliverableZoneIds;
String? deliverableZones;

UserDetails.fromJson(Map<String, dynamic> json) {
id = json['user_id'] ?? json['id'];
bonusType = json['bonus_type'];
serviceableZipcodes = json['serviceable_zipcodes'] ?? "";
serviceableCities = json['serviceable_cities'] ?? "";
frontLicenceImage = json['front_licence_image'] ?? "";
backLicenceImage = json['back_licence_image'] ?? "";

roleId = json['role_id'];
ipAddress = json['ip_address'];
username = json['username'];
email = json['email'];
mobile = json['mobile'];
image = json['image'] ?? "";
balance = double.tryParse((json['balance'] ?? 0).toString().isEmpty
? "0"
: (json['balance'] ?? 0).toString());

activationSelector = json['activation_selector'].toString();
activationCode = json['activation_code'].toString();
forgottenPasswordSelector = json['forgotten_password_selector'].toString();
forgottenPasswordCode = json['forgotten_password_code'].toString();
forgottenPasswordTime = json['forgotten_password_time'].toString();
rememberSelector = json['remember_selector'];
rememberCode = json['remember_code'];
lastLogin = json['last_login'].toString();
active = json['active'].toString();
company = json['company'];
address = json['address'];
bonus = json['bonus'].toString();
cashReceived = json['cash_received'].toString();
dob = json['dob'];
countryCode = json['country_code'].toString();
city = json['city'];
area = json['area'];
street = json['street'];
pincode = json['pincode'].toString();
apikey = json['apikey'].toString();
referralCode = json['referral_code'];
friendsCode = json['friends_code'];
fcmId = json['fcm_id'] != null ? List<String>.from(json['fcm_id']) : [];
latitude = json['latitude'];
longitude = json['longitude'];
type = json['type'];
isNotificationOn = int.tryParse(json['is_notification_on'].toString()) ?? 1;
deliverableZones = json['zones'] ?? "";
deliverableZoneIds = json['serviceable_zones'] ?? "";
}

Map<String, dynamic> toJson() {
final Map<String, dynamic> data = {};
data['user_id'] = id;
data['zones'] = deliverableZones;
data['serviceable_zones'] = deliverableZoneIds;
data['serviceable_zipcodes'] = serviceableZipcodes;
data['serviceable_cities'] = serviceableCities;
data['bonus_type'] = bonusType;
data['front_licence_image'] = frontLicenceImage;
data['back_licence_image'] = backLicenceImage;
data['ip_address'] = ipAddress;
data['username'] = username;
data['email'] = email;
data['mobile'] = mobile;
data['image'] = image;
data['balance'] = balance;
data['activation_selector'] = activationSelector;
data['activation_code'] = activationCode;
data['forgotten_password_selector'] = forgottenPasswordSelector;
data['forgotten_password_code'] = forgottenPasswordCode;
data['forgotten_password_time'] = forgottenPasswordTime;
data['remember_selector'] = rememberSelector;
data['remember_code'] = rememberCode;
data['last_login'] = lastLogin.toString();
data['active'] = active;
data['company'] = company;
data['address'] = address;
data['bonus'] = bonus;
data['cash_received'] = cashReceived;
data['dob'] = dob;
data['country_code'] = countryCode;
data['city'] = city;
data['area'] = area;
data['street'] = street;
data['pincode'] = pincode;
data['apikey'] = apikey;
data['referral_code'] = referralCode;
data['friends_code'] = friendsCode;
data['fcm_id'] = fcmId;
data['latitude'] = latitude;
data['longitude'] = longitude;
data['type'] = type;
data['is_notification_on'] = isNotificationOn;
return data;
}

UserDetails copyWith(
{int? id,
String? ipAddress,
String? username,
String? email,
String? mobile,
String? image,
double? balance,
String? activationSelector,
String? activationCode,
String? forgottenPasswordSelector,
String? forgottenPasswordCode,
String? forgottenPasswordTime,
String? rememberSelector,
String? rememberCode,
String? lastLogin,
int? active,
String? company,
String? address,
String? bonus,
int? cashReceived,
String? dob,
String? countryCode,
String? city,
String? area,
String? street,
String? pincode,
String? apikey,
String? referralCode,
String? friendsCode,
String? fcmId,
String? latitude,
String? longitude,
String? type,
int? isNotificationOn}) {
return UserDetails(
id: id ?? this.id,
ipAddress: ipAddress ?? this.ipAddress,
username: username ?? this.username,
email: email ?? this.email,
mobile: mobile ?? this.mobile,
image: image ?? this.image,
balance: balance ?? this.balance,
activationSelector: activationSelector ?? this.activationSelector,
activationCode: activationCode ?? this.activationCode,
forgottenPasswordSelector:
forgottenPasswordSelector ?? this.forgottenPasswordSelector,
forgottenPasswordCode:
forgottenPasswordCode ?? this.forgottenPasswordCode,
forgottenPasswordTime:
forgottenPasswordTime ?? this.forgottenPasswordTime,
rememberSelector: rememberSelector ?? this.rememberSelector,
rememberCode: rememberCode ?? this.rememberCode,
lastLogin: lastLogin ?? this.lastLogin,
active: active ?? this.active,
company: company ?? this.company,
address: address ?? this.address,
bonus: bonus ?? this.bonus,
cashReceived: cashReceived ?? this.cashReceived,
dob: dob ?? this.dob,
countryCode: countryCode ?? this.countryCode,
city: city ?? this.city,
area: area ?? this.area,
street: street ?? this.street,
pincode: pincode ?? this.pincode,
apikey: apikey ?? this.apikey,
referralCode: referralCode ?? this.referralCode,
friendsCode: friendsCode ?? this.friendsCode,
fcmId: fcmId ?? this.fcmId,
latitude: latitude ?? this.latitude,
longitude: longitude ?? this.longitude,
type: type ?? this.type,
isNotificationOn: isNotificationOn ?? this.isNotificationOn);
}
}
