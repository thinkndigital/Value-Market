import 'package:value_market_customer/main.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:get/get.dart';

class ChatController extends GetxController {
  bool showNotification(String senderId) {
    if (currentChatUserId == senderId &&
        Get.currentRoute == Routes.chatScreen) {
      // Chat screen for this user is open, don't show notification
      return false;
    }
    return true;
  }
}
