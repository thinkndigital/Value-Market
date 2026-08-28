import UIKit
import Flutter
import GoogleMaps
import Firebase
@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool { 
      //google maps api key
    GMSServices.provideAPIKey("ENTER_YOUR_IOS_MAP_KEY_HERE")
     FirebaseApp.configure()
    GeneratedPluginRegistrant.register(with: self)
   
  
    
    if #available(iOS 10.0, *) {
      // For iOS 10 display notification (sent via APNS)
      UNUserNotificationCenter.current().delegate = self

      let authOptions: UNAuthorizationOptions = [.alert, .badge, .sound]
      UNUserNotificationCenter.current().requestAuthorization(
        options: authOptions,
        completionHandler: {_, _ in })
    } else {
      let settings: UIUserNotificationSettings =
      UIUserNotificationSettings(types: [.alert, .badge, .sound], categories: nil)
      application.registerUserNotificationSettings(settings)
    }
   
    
    application.registerForRemoteNotifications()
    
    Messaging.messaging().token { token, error in
      if let error = error {
      } else if let token = token {
//        self.fcmRegTokenMessage.text  = "Remote FCM registration token: \(token)"
      }
    }
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
