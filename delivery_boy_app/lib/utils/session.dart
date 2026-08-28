import 'package:flutter/cupertino.dart';
import 'package:shared_preferences/shared_preferences.dart';


class Session with ChangeNotifier {
SharedPreferences pref;

Session(this.pref);

saveData<T>(String key, T value) {
if (value is String) {
pref.setString(key, value);
}
if (value is bool) {
pref.setBool(key, value);
}
if (value is int) {
pref.setInt(key, value);
}
if (value is double) {
pref.setDouble(key, value);
}
if (value is List<String>) {
pref.setStringList(key, value);
}
}

getStringData(String key) {
return pref.getString(key) ?? '';
}

getIntData(String key) {
return pref.getInt(key) ?? 0;
}

getDoubleData(String key) {
return pref.getDouble(key) ?? 0.0;
}

getBoolData(String key) {
return pref.getBool(key);
}

removeData(String key) {
pref.remove(key);
}

}
