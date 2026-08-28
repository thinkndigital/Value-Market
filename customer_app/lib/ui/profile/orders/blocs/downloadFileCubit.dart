import 'dart:io';

import 'package:dio/dio.dart';
import 'package:eshop_plus/ui/profile/orders/repositories/orderRepository.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:path_provider/path_provider.dart';

abstract class DownloadFileState {}

class DownloadFileInitial extends DownloadFileState {}

class DownloadFileInProgress extends DownloadFileState {
  final double uploadedPercentage;

  DownloadFileInProgress(this.uploadedPercentage);
}

class DownloadFileSuccess extends DownloadFileState {
  final String downloadedFilePath;
  DownloadFileSuccess(this.downloadedFilePath);
}

class DownloadFileProcessCanceled extends DownloadFileState {}

class DownloadFileFailure extends DownloadFileState {
  final String errorMessage;

  DownloadFileFailure(this.errorMessage);
}

class DownloadFileCubit extends Cubit<DownloadFileState> {
  DownloadFileCubit() : super(DownloadFileInitial());

  final CancelToken _cancelToken = CancelToken();

  void _downloadedFilePercentage(double percentage) {
    emit(DownloadFileInProgress(percentage));
  }

  void downloadFile({
    required String fileUrl,
    String fName = '',
  }) async {
    emit(DownloadFileInProgress(0.0));
    try {
      //if user has given permission to download and view file

      //storing the fie temp
      final Directory tempDir = await getTemporaryDirectory();
      String fileName = fName.isNotEmpty
          ? fName
          : fileUrl.substring(fileUrl.lastIndexOf('/') + 1);
      final tempFileSavePath = "${tempDir.path}/$fileName";

      if (await Utils.hasStoragePermissionGiven()) {
        await OrderRepository().downloadFile(
            cancelToken: _cancelToken,
            savePath: tempFileSavePath,
            updateDownloadedPercentage: _downloadedFilePercentage,
            url: fileUrl);

        //download file
        String downloadFilePath = await Utils.getDowanloadFilePath(fileName);

        await Utils.writeFileFromTempStorage(
            sourcePath: tempFileSavePath, destinationPath: downloadFilePath);

        emit(DownloadFileSuccess(downloadFilePath));
      } else {
        //if user does not give permission to store files in download directory
        emit(DownloadFileFailure(storagePermissionNotGrantedKey));
      }
    } catch (e) {
      if (_cancelToken.isCancelled) {
        emit(DownloadFileProcessCanceled());
      } else {
        emit(DownloadFileFailure(e.toString()));
      }
    }
  }

  void cancelDownloadProcess() {
    _cancelToken.cancel();
  }
}
