import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';

import 'permissions.dart';

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
  List? fcmId;
  String? latitude;
  String? longitude;
  String? type;
  List<SellerData>? sellerData;
  List<StoreData>? storeData;
  List<ChatMessage>? messages;
  int? unreadCount;
  late int isNotificationOn;
  String? deliverableZoneIds;
  String? deliverableZones;

  UserDetails({
    id,
    roleId,
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
    sellerData,
    storeData,
    this.messages,
    this.unreadCount,
    this.isNotificationOn = 1,
    deliverableZones,
    deliverableZoneIds,
  });

  UserDetails.fromJson(Map<String, dynamic> json) {
    id = json['user_id'] ?? json['id'];

    roleId = json['role_id'];
    ipAddress = json['ip_address'];
    username = json['username'];
    email = json['email'];
    mobile = json['mobile'];
    image = json['image'];
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
    if (json['seller_data'] != null) {
      sellerData = <SellerData>[];
      json['seller_data'].forEach((v) {
        sellerData!.add(SellerData.fromJson(v));
      });
    }
    if (json['store_data'] != null) {
      storeData = <StoreData>[];
      json['store_data'].forEach((v) {
        storeData!.add(StoreData.fromJson(v));
      });
    }
    isNotificationOn = int.tryParse(json['is_notification_on'].toString()) ?? 1;
    deliverableZones = json['zones'] ?? "";
    deliverableZoneIds = json['serviceable_zones'] ?? "";
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['user_id'] = id;
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
    if (sellerData != null) {
      data['seller_data'] = sellerData!.map((v) => v.toJson()).toList();
    }
    if (storeData != null) {
      data['store_data'] = storeData!.map((v) => v.toJson()).toList();
    }
    data['is_notification_on'] = isNotificationOn;
    data['zones'] = deliverableZones;
    data['serviceable_zones'] = deliverableZoneIds;
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
      List<SellerData>? sellerData,
      List<StoreData>? storeData,
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
        sellerData: sellerData ?? this.sellerData,
        storeData: storeData ?? this.storeData,
        isNotificationOn: isNotificationOn ?? this.isNotificationOn);
  }
}

class SellerData {
  int? userId;
  String? nationalIdentityCard;
  String? authorizedSignature;
  String? disk;
  String? panNumber;
  int? status;
  String? logo;
  String? addressProof;

  SellerData(
      {userId,
      nationalIdentityCard,
      authorizedSignature,
      disk,
      panNumber,
      status,
      logo,
      addressProof});

  SellerData.fromJson(Map<String, dynamic> json) {
    userId = json['user_id'];
    nationalIdentityCard = json['national_identity_card'];
    authorizedSignature = json['authorized_signature'];
    disk = json['disk'];
    panNumber = json['pan_number'];
    status = json['status'];
    logo = json['logo'];
    addressProof = json['address_proof'];
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['user_id'] = userId;
    data['national_identity_card'] = nationalIdentityCard;
    data['authorized_signature'] = authorizedSignature;
    data['disk'] = disk;
    data['pan_number'] = panNumber;
    data['status'] = status;
    data['logo'] = logo;
    data['address_proof'] = addressProof;
    return data;
  }
}

class StoreData {
  int? id;
  int? sellerId;
  int? userId;
  int? storeId;
  String? slug;
  String? categoryIds;
  String? storeName;
  String? storeDescription;
  String? logo;
  String? storeThumbnail;
  String? disk;
  String? storeUrl;
  int? noOfRatings;
  String? rating;
  String? bankName;
  String? bankCode;
  String? accountName;
  String? accountNumber;
  String? addressProof;
  String? taxName;
  String? taxNumber;
  Permissions? permissions;
  int? commission;
  String? latitude;
  String? longitude;
  int? status;
  String? city, zipcode, cityId, zipcodeId;
  List<dynamic>? otherDocuments;
  int? deliverableType; //1 ->all , 2->included , 3->excluded
  String? deliverableZones; // return id of zones
  String? zoneIds; // return id of zones
  String? zones; // return name of zones

  StoreData({
    id,
    city,
    zipcode,
    cityId,
    zipcodeId,
    sellerId,
    userId,
    storeId,
    slug,
    categoryIds,
    storeName,
    storeDescription,
    logo,
    storeThumbnail,
    disk,
    storeUrl,
    noOfRatings,
    rating,
    bankName,
    bankCode,
    accountName,
    accountNumber,
    addressProof,
    taxName,
    taxNumber,
    permissions,
    commission,
    latitude,
    longitude,
    status,
    otherDocuments,
    deliverableType,
    deliverableZones,
    zoneIds,
    zones,
  });

  StoreData.fromJson(Map<String, dynamic> json) {
    id = json['id'];
    cityId = (json['city_id'] ?? 0).toString();
    zipcodeId = (json['zipcode_id'] ?? 0).toString();
    city = json['city'] ?? "";
    zipcode = json['zipcode'] ?? "";
    sellerId = json['seller_id'];
    userId = json['user_id'];
    storeId = json['store_id'];
    slug = json['slug'];
    categoryIds = json['category_ids'];
    storeName = json['store_name'];
    storeDescription = json['store_description'];
    logo = json['logo'];
    storeThumbnail = json['store_thumbnail'];
    disk = json['disk'];
    storeUrl = json['store_url'];
    noOfRatings = json['no_of_ratings'];
    rating = json['rating'].toString();
    bankName = json['bank_name'];
    bankCode = json['bank_code'];
    accountName = json['account_name'];
    accountNumber = json['account_number'];
    addressProof = json['address_proof'];
    taxName = json['tax_name'];
    taxNumber = json['tax_number'];
    permissions = json['permissions'] != null
        ? Permissions.fromJson(json['permissions'])
        : null;
    commission = json['commission'];
    latitude = json['latitude'];
    longitude = json['longitude'];
    status = json['status'];
    otherDocuments = json['other_documents'] != null
        ? List<String>.from(json['other_documents'])
        : [];
    deliverableType = json['deliverable_type'] as int? ?? 1;
    deliverableZones = json['deliverable_zones'] as String?;
    zoneIds = json['zone_ids'] as String?;
    zones = json['zones'] as String?;
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = {};
    data['id'] = id;
    data['seller_id'] = sellerId;
    data['user_id'] = userId;
    data['store_id'] = storeId;
    data['slug'] = slug;
    data['category_ids'] = categoryIds;
    data['store_name'] = storeName;
    data['store_description'] = storeDescription;
    data['logo'] = logo;
    data['store_thumbnail'] = storeThumbnail;
    data['disk'] = disk;
    data['store_url'] = storeUrl;
    data['no_of_ratings'] = noOfRatings;
    data['rating'] = rating;
    data['bank_name'] = bankName;
    data['bank_code'] = bankCode;
    data['account_name'] = accountName;
    data['account_number'] = accountNumber;
    data['address_proof'] = addressProof;
    data['tax_name'] = taxName;
    data['tax_number'] = taxNumber;
    data['city'] = city;
    data['zipcode'] = zipcode;
    data['zipcode_id'] = zipcodeId;
    data['city_id'] = cityId;
    if (permissions != null) {
      data['permissions'] = permissions!.toJson();
    }
    data['commission'] = commission;
    data['latitude'] = latitude;
    data['longitude'] = longitude;
    data['status'] = status;
    data['other_documents'] = otherDocuments;
    data['deliverable_type'] = deliverableType;
    data['deliverable_zones'] = deliverableZones;
    data['zone_ids'] = zoneIds;
    data['zones'] = zones;
    return data;
  }
}
