# Demo image sources and licenses

The demo dataset uses **17 real JPEG photographs** stored locally at
`storage/app/public/media/demo/images/`. No placeholder images, remote hotlinks,
generated images or broken URLs are used. `DemoMediaSeeder` validates each
source file with `getimagesize()` and `image/jpeg`, copies the bytes to the
configured public media disk under `media/2026/11/stock-<slug>.jpg`, and records
the SHA-256 checksum in the private demo ownership manifest.

Pexels images are used under the [Pexels License](https://www.pexels.com/license/),
which permits free use and does not require attribution. Attribution is recorded
here for provenance. Wikimedia Commons images are used under their respective
Creative Commons licenses; the required attribution and license links are
included below.

## Inventory

| Local file | Source | Creator | License | SHA-256 |
| --- | --- | --- | --- | --- |
| `adult-education.jpg` | [Pexels 7983565](https://www.pexels.com/photo/7983565/) — *Retirees Enjoying Adult Education* | Kampus Production | [Pexels License](https://www.pexels.com/license/) | `748c93409ecbff4ebad48ea1a681764e344b8962bc853f5ad5a0f94e7b7288fc` |
| `classroom.jpg` | [Pexels 7395893](https://www.pexels.com/photo/7395893/) — *Back View of the Students Sitting Inside the Classroom* | cottonbro studio | [Pexels License](https://www.pexels.com/license/) | `f995b779c3aa7c1d78f99e198a5457178ad39f625fdb61e4b4a11166c5acd0d0` |
| `community-garden.jpg` | [Pexels 39778110](https://www.pexels.com/photo/39778110/) — *Community Gardening Project in Bloomington* | Zack Gilbert | [Pexels License](https://www.pexels.com/license/) | `165765aeac9479fb183c8ab7579128b763123be92dd411cf63aa92bd67e16a4e` |
| `donation-sorting.jpg` | [Pexels 6590915](https://www.pexels.com/photo/6590915/) — *Man and Woman Checking Goods in the Box* | cottonbro studio | [Pexels License](https://www.pexels.com/license/) | `238104d86a31666f02e6d44aa36d51892131b2478bcb413b95d533183d4aa063` |
| `farmer-harvest.jpg` | [Pexels 4975362](https://www.pexels.com/photo/4975362/) — *Elderly Farmer Collecting Vegetables on Farm* | Gustavo Fring | [Pexels License](https://www.pexels.com/license/) | `3a30236d6c8970b5b146f71751431b8dda82252e67c516bfcb7e665f24ebd6c3` |
| `food-donations.jpg` | [Pexels 6994946](https://www.pexels.com/photo/6994946/) — *A Close-Up Shot of Food Donations* | Julia M Cameron | [Pexels License](https://www.pexels.com/license/) | `b6bd8e9c4b0dc5095629fe5852e2b045eb304c5e34e5781581945853bf4cca4a` |
| `food-handover.jpg` | [Pexels 6995221](https://www.pexels.com/photo/6995221/) — *A Person Handing Over a Box with Food* | Julia M Cameron | [Pexels License](https://www.pexels.com/license/) | `1e9994c2958616ff95a0f0d74711bc3c2d11d54838422dbae84d0ea6aa7f4d57` |
| `food-packing.jpg` | [Pexels 6591165](https://www.pexels.com/photo/6591165/) — *Volunteers Boxing Foods to Be Distributed* | cottonbro studio | [Pexels License](https://www.pexels.com/license/) | `d112931e0c0e6335f1186fa69f41d90fb274ad903472f1f91c31ff3eeba87ddc` |
| `handwashing.jpg` | [Pexels 545013](https://www.pexels.com/photo/545013/) — *Person Washing Hands* | Burst | [Pexels License](https://www.pexels.com/license/) | `89e1b7754615454f2988a56321d1119a3d4b161079aebe008a59c8cb3aa516fa` |
| `health-check.jpg` | [Pexels 7659564](https://www.pexels.com/photo/7659564/) — *Healthcare Worker Measuring a Patient's Blood Pressure* | Thirdman | [Pexels License](https://www.pexels.com/license/) | `db9dce8fb89c25b678ddad8807a2435b3dfb22303cd44539135d55ad0f308c6d` |
| `lecture.jpg` | [Pexels 8199141](https://www.pexels.com/photo/8199141/) — *Students Sitting Inside a Classroom* | Yan Krukau | [Pexels License](https://www.pexels.com/license/) | `6a7eb12c58a609ecef31d2435f39d0387e31aba5ec62ed60e1e0088c935f1312` |
| `olive-farming.jpg` | [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:Palestinian_farmer_plants_olive_trees_in_Beit_Omman_(2010).jpg) — *Palestinian farmer plants olive trees in Beit Omman (2010)* | Palestine Solidarity Project | [CC BY-SA 2.0](https://creativecommons.org/licenses/by-sa/2.0/) | `8bcfacbbcceda7fb54b2db12a62fb8559b544833c80be78beb44e5526ae85275` |
| `planting.jpg` | [Pexels 5029923](https://www.pexels.com/photo/5029923/) — *People Planting Plant Together* | Anna Shvets | [Pexels License](https://www.pexels.com/license/) | `f41b251902a5779b8b175b735176de89df1c17984e78a8bbf2136584440cecc6` |
| `soap.jpg` | [Pexels 10221840](https://www.pexels.com/photo/10221840/) — *Washing Hands with Soap* | Ron Lach | [Pexels License](https://www.pexels.com/license/) | `7f4a639b25b9245975a26fa9e6647da4b6774984d5126c48fc753ecf33b4158a` |
| `tree-planting.jpg` | [Pexels 7656721](https://www.pexels.com/photo/7656721/) — *Volunteers on Tree Planting* | Thirdman | [Pexels License](https://www.pexels.com/license/) | `61e520d8a08f9b75fcd5f251dee42bb1d6c3987c77d49023e61501cefa2a24d4` |
| `vegetable-harvest.jpg` | [Pexels 6447910](https://www.pexels.com/photo/6447910/) — *People Harvesting Green Leafy Vegetables* | Wim Van den Brande | [Pexels License](https://www.pexels.com/license/) | `ba463b2dc9837061b2f94d0d40ac48db30915ff21f3e07b6c0412e184c1026ee` |
| `water-well.jpg` | [Wikimedia Commons](https://commons.wikimedia.org/wiki/File:IHM_water-well_1.jpeg) — *IHM water-well 1* | hatul | [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/) | `e749ff97874dcc43f80874d79d936e73574f4a914293397aa1863eaf58e884d8` |

## Use constraints

- Captions and alt text in `media_translations` describe only the visible subject; they never claim that the photograph documents a real PCDR activity, beneficiary, partner or field visit.
- The source files are committed under `storage/app/public/media/demo/images/` so seeding does not depend on network access.
- The copied media files are created under deterministic paths and tracked by checksum. Cleanup deletes a copied file only when it still exists and its checksum matches the manifest.
- The CC BY-SA files retain their attribution and license links above. Do not remove that attribution if these images are reused outside this demo dataset.
