import 'package:intl/intl.dart';

class DateTimeUtils {
  static String formatDate(String dateString, String dateFormat) {
    // Define the input format
    DateFormat inputFormat = DateFormat('dd-MM-yyyy');
    // Parse the date string into a DateTime object
    DateTime dateTime = inputFormat.parse(dateString);
    // Define the output format
    DateFormat outputFormat = DateFormat(dateFormat);
    // Format the DateTime object into the desired output format
    return outputFormat.format(dateTime);
  }
}
