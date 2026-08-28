import 'package:eshop_plus/main.dart';
import 'package:eshop_plus/core/routes/routes.dart';
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
