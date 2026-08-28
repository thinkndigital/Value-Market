import 'package:eshop_plus/ui/home/brand/brand.dart';
import 'package:eshop_plus/ui/home/brand/brandRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class BrandsState {}

class BrandsInitial extends BrandsState {}

class BrandsFetchInProgress extends BrandsState {}

class BrandsFetchSuccess extends BrandsState {
  final int total;
  final List<Brand> brands;
  final bool fetchMoreError;
  final bool fetchMoreInProgress;

  BrandsFetchSuccess(
      {required this.brands,
      required this.fetchMoreError,
      required this.fetchMoreInProgress,
      required this.total});

  BrandsFetchSuccess copyWith(
      {bool? fetchMoreError,
      bool? fetchMoreInProgress,
      int? total,
      List<Brand>? brands}) {
    return BrandsFetchSuccess(
        brands: brands ?? this.brands,
        fetchMoreError: fetchMoreError ?? this.fetchMoreError,
        fetchMoreInProgress: fetchMoreInProgress ?? this.fetchMoreInProgress,
        total: total ?? this.total);
  }
}

class BrandsFetchFailure extends BrandsState {
  final String errorMessage;

  BrandsFetchFailure(this.errorMessage);
}

class BrandsCubit extends Cubit<BrandsState> {
  final BrandRepository _brandRepository = BrandRepository();

  BrandsCubit() : super(BrandsInitial());

  void getBrands({required int storeId, String? brandIds}) async {
    emit(BrandsFetchInProgress());
    try {
      final result = await _brandRepository.getBrands(
          storeId: storeId, brandIds: brandIds);
      if (!isClosed)
        emit(BrandsFetchSuccess(
            brands: result.brands,
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: result.total));
    } catch (e) {
      if (!isClosed) emit(BrandsFetchFailure(e.toString()));
    }
  }

  bool hasMore() {
    if (state is BrandsFetchSuccess) {
      return (state as BrandsFetchSuccess).brands.length <
          (state as BrandsFetchSuccess).total;
    }
    return false;
  }

  void loadMore({required int storeId, String? brandIds}) async {
    if (state is BrandsFetchSuccess) {
      if ((state as BrandsFetchSuccess).fetchMoreInProgress) {
        return;
      }
      try {
        emit((state as BrandsFetchSuccess).copyWith(fetchMoreInProgress: true));

        final moreBrands = await _brandRepository.getBrands(
            storeId: storeId,
            brandIds: brandIds,
            offset: (state as BrandsFetchSuccess).brands.length);

        final currentState = (state as BrandsFetchSuccess);

        List<Brand> brands = currentState.brands;

        brands.addAll(moreBrands.brands);

        emit(BrandsFetchSuccess(
            fetchMoreError: false,
            fetchMoreInProgress: false,
            total: moreBrands.total,
            brands: brands));
      } catch (e) {
        emit((state as BrandsFetchSuccess)
            .copyWith(fetchMoreInProgress: false, fetchMoreError: true));
      }
    }
  }
}
