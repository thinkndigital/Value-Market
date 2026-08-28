import 'package:eshopplus_seller/core/constants/appConstants.dart';
import 'package:intl/intl.dart';

class DateTimeUtils {
  static getFormattedDateTime(String datetime,
      {bool isReturnOnlyDate = false}) {
    if (datetime.trim().isNotEmpty && !datetime.contains(" ")) {
      datetime = "$datetime 00:00:00";
    }
    DateTime? mainDatetime;
    try {
      mainDatetime = apiDateTimeFormat.parse(datetime);
    } catch (e) {}

    return mainDatetime == null
        ? datetime
        : DateFormat(isReturnOnlyDate ? "MMM dd, yyyy" : "dd-MMM yyyy, HH:mm")
            .format(mainDatetime);
  }
}
