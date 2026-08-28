import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';

import 'package:shimmer/shimmer.dart';

class CustomImageWidget extends StatelessWidget {
  final double? width;
  final double? height;
  final String url;
  final BoxFit? boxFit;
  final double? borderRadius;
  bool? isCircularImage;
  Widget? child;

  CustomImageWidget({
    super.key,
    this.width,
    this.height,
    required this.url,
    this.boxFit = BoxFit.cover,
    this.borderRadius,
    this.isCircularImage = false,
    this.child,
  });

  @override
  Widget build(BuildContext context) {
    String? extension = url.split('.').last.toLowerCase();
    final BoxFit effectiveBoxFit = boxFit ?? BoxFit.contain;
    try {
      return isCircularImage == true
          ? CircularImageWithShimmer(
              imageUrl: url,
              radius: borderRadius ?? 0,
            )
          : Container(
              width: width,
              height: height,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(borderRadius ?? 0),
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(borderRadius ?? 0),
                child: extension == "svg"
                    ? Utils.getSvgImage(url,
                        height: height, width: width, boxFit: effectiveBoxFit)
                    : CachedNetworkImage(
                        imageUrl: url,
                        fit: effectiveBoxFit,
                        placeholder: (context, url) => Shimmer.fromColors(
                              baseColor: Colors.grey[300]!,
                              highlightColor: Colors.grey[100]!,
                              child: Container(
                                width: width,
                                height: height,
                                color: Colors.white,
                              ),
                            ),
                        imageBuilder: (context, imageProvider) => Container(
                            width: width,
                            height: height,
                            decoration: BoxDecoration(
                              borderRadius:
                                  BorderRadius.circular(borderRadius ?? 0),
                              image: DecorationImage(
                                fit: effectiveBoxFit,
                                image: imageProvider,
                              ),
                            ),
                            child: child),
                        errorWidget: (context, url, error) {
                          return Center(
                            child: Icon(
                              Icons.error,
                              color: Theme.of(context).colorScheme.error,
                            ),
                          );
                        }),
              ),
            );
    } catch (e) {
      return SizedBox(
        width: width,
        height: height,
        child: Icon(
          Icons.error,
          color: Theme.of(context).colorScheme.error,
        ),
      );
    }
  }
}

class CircularImageWithShimmer extends StatelessWidget {
  final String imageUrl;
  final double radius;

  const CircularImageWithShimmer({
    Key? key,
    required this.imageUrl,
    this.radius = 24.0,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    String? extension = imageUrl.split('.').last.toLowerCase();

    if (extension == "svg") {
      return Utils.getSvgImage(
        imageUrl,
        width: radius * 2,
        height: radius * 2,
      );
    } else {
      return CachedNetworkImage(
        imageUrl: imageUrl,
        imageBuilder: (context, imageProvider) => CircleAvatar(
          radius: radius,
          backgroundImage: imageProvider,
        ),
        placeholder: (context, url) => Shimmer.fromColors(
          baseColor: Colors.grey[300]!,
          highlightColor: Colors.grey[100]!,
          child: Container(
            width: radius * 2,
            height: radius * 2,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.grey[300],
            ),
          ),
        ),
        errorWidget: (context, url, error) => CircleAvatar(
          radius: radius,
          backgroundColor: Colors.grey[300],
          child: Icon(
            Icons.error,
            color: Colors.red,
          ),
        ),
      );
    }
  }
}
