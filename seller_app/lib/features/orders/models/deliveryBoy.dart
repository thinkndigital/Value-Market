class DeliveryBoy {
  int? id, status;
  String? name, username, email, img, balance, mobile, city, street;

  DeliveryBoy({
    this.id,
    this.name,
    this.username,
    this.email,
    this.img,
    this.status,
    this.balance,
    this.mobile,
    this.city,
    this.street,
  });

  DeliveryBoy.fromJson(Map<String, dynamic> json) {
    id = json["id"];
    name = json["name"];
    username = json["username"];
    email = json["email"];
    img = json["image"];
    status = json["status"];
    mobile = json["mobile"];
    city = json["city"] ?? "";
    street = json["street"] ?? "";
    balance = json["balance"].toString();
  }

  @override
  String toString() {
    return name!;
  }

  ///this method will prevent the override of toString
  String userAsString() {
    return '#$id $name';
  }
}
