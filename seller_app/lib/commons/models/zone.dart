import '../../features/auth/models/zipcode.dart';

class Zone {
  int? id;
  String? zonename;
  List<Zipcode>? zipcodeList;
  List<Zipcode>? cityList;

  Zone({
    required this.id,
    required this.zonename,
    required this.zipcodeList,
    required this.cityList,
  });
  Zone.fromJson(Map<String, dynamic> json) {
    id = json["zone_id"];
    if (json.containsKey("zone_name")) zonename = json["zone_name"] ?? "";
    zipcodeList = [];
    cityList = [];
    if (json.containsKey("zipcodes")) {
      zipcodeList =
          (json["zipcodes"] as List).map((e) => Zipcode.fromJson(e)).toList();
    }
    if (json.containsKey("cities")) {
      cityList =
          (json["cities"] as List).map((e) => Zipcode.fromJson(e)).toList();
    }
  }
}
