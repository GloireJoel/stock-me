<?php

namespace App\Controller;

use App\Entity\Operation;
use App\Entity\Products;
use App\Form\ProductsType;
use App\Form\SellType;
use App\Repository\OperationRepository;
use App\Repository\ProductsRepository;
use App\Service\ImgUploader;
use Doctrine\ORM\EntityManagerInterface;
use Konekt\PdfInvoice\InvoicePrinter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/products')]
class ProductsController extends AbstractController
{

    #[Route('/operations', name: 'products_operations', methods: ['GET'])]
    public function operations(OperationRepository $operationRepository): Response
    {
        return $this->render('operation/index.html.twig', [
            'operations' => $operationRepository->findAll()
        ]);

    }

    #Display the table of products in GET method, check and persist the new Product in POST method
    #[Route('/', name: 'products_index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MEMBER', message: 'Vous devez confirmer votre email ou être connecté pour acceder à cette partie du site', statusCode: 403)]
    public function indexAndFormAddProduct(ProductsRepository $productsRepository, Request $request, EntityManagerInterface $entityManager, ImgUploader $uploader): Response
    {
        $product = new Products();
        $form = $this->createForm(ProductsType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check user roles //
            if (!$this->isGranted('ROLE_MANAGER')){
                throw $this->createAccessDeniedException('Vous devez être manager pour créer un produit');
            }
            // Check if product already exist by name query : display error if already exist //
            if($productsRepository->findOneBy(['name'=>$product->getName()])){
                $this->addFlash('error', 'Produit déjà existant');
                return $this->redirectToRoute('products_index');
            }
            // Give new name if an image has been uploaded //
            $file = $form['imagePath']->getData();
            if($file instanceof UploadedFile){
                $filename = $uploader->getFileName($file);
                $product->setImagePath("image/$filename");
            }
            $entityManager->persist($product);
            $entityManager->flush();
            $this->addFlash('success', 'Article ajouté avec succés');
            return $this->redirectToRoute('products_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('products/index.html.twig', [
            'products' => $productsRepository->findAll(),
            'form' => $form->createView()
        ]);
    }

    #Show the product details
    #[Route('/{id}', name: 'products_show', methods: ['GET'])]
    #[IsGranted('ROLE_MEMBER', message: 'Vous devez confirmer votre email pour acceder à cette partie du site', statusCode: 403)]
    public function show(Products $product, ProductsRepository $repository): Response
    {
        return $this->render('products/show.html.twig', [
            'product' => $product,
            'products' => $repository->findAll()
        ]);
    }

    #Display the form for edit a product in GET method, check and persist the Product edited in POST method
    #[Route('/{id}/edit', name: 'products_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MANAGER', message: 'Vous devez être manager pour accéder à cette partie du site', statusCode: 403)]
    public function edit(string $id, Request $request, Products $product, EntityManagerInterface $entityManager, ImgUploader $uploader): Response
    {
        $productRepository = $entityManager->getRepository(Products::class);
        $nameBeforePost = $productRepository->find($id)->getName();

        $form = $this->createForm(ProductsType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if product name change in field post and if is already in db //
            if($nameBeforePost !== $product->getName() && $productRepository->findOneBy(['name'=>$product->getName()])){
                $this->addFlash('error', 'Nom de produit déjà existant');
                return $this->redirectToRoute('products_edit', ['id'=>$request->get('id')]);
            }

            // Give new name to the image if user uploaded one //
            $file = $form['imagePath']->getData();
            if($file instanceof UploadedFile){
                $filename = $uploader->getFileName($file);
                $fullPath = "image/$filename";
                $product->setImagePath($fullPath);
            }
            $entityManager->flush();
            $this->addFlash('success', 'Article édité avec succès');
            return $this->redirectToRoute('products_show', ['id'=> $request->get('id')], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('products/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #Display the form for edit a product in GET method, check and persist the Product edited in POST method , requirements: ['id' => '\d+']
    #[Route('/{id}/sell', name: 'products_sell', methods: ['GET', 'POST'])]
    public function sell(string $id, Request $request, Products $product, EntityManagerInterface $entityManager): Response
    {
        $productRepository = $entityManager->getRepository(Products::class);
        $quantity = $productRepository->find($id)->getQuantity();

        $operation = new Operation();

        $form = $this->createForm(SellType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if product name change in field post and if is already in db //
            if($quantity <  $form->get('quantity')->getData()){
                $this->addFlash('error', 'Stock Insuffisant');
                return $this->redirectToRoute('products_sell', ['id'=>$request->get('id')]);
            }
            $stock = $quantity - $form->get('quantity')->getData();
            $operation->setName("OUTPUT");
            $operation->setPrice($form->get('price')->getData());
            $operation->setQuantity($form->get('quantity')->getData());
            $product->setQuantity($stock);
            $entityManager->persist($operation);
            $this->printInvoice();
            $entityManager->flush();
            $this->addFlash('success', 'Article vendu avec succès');
            return $this->redirectToRoute('products_show', ['id'=> $request->get('id')], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('products/sell.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #Display the form for edit a product in GET method, check and persist the Product edited in POST method , requirements: ['id' => '\d+']
    #[Route('/{id}/add', name: 'products_add', methods: ['GET', 'POST'])]
    public function add(string $id, Request $request, Products $product, EntityManagerInterface $entityManager): Response
    {
        $operation = new Operation();
        $productRepository = $entityManager->getRepository(Products::class);
        $quantity = $productRepository->find($id)->getQuantity();

        $form = $this->createForm(SellType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stock = $quantity + $form->get('quantity')->getData();
            $operation->setName("INPUT");
            $operation->setQuantity($form->get('quantity')->getData());
            $operation->setPrice($form->get('price')->getData());
            $product->setQuantity($stock);
            $entityManager->persist($operation);
            $product->setQuantity($stock);
            $entityManager->flush();
            $this->addFlash('success', 'Article vendu avec succès');
            return $this->redirectToRoute('products_show', ['id'=> $request->get('id')], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('products/sell.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'products_delete', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER', message: 'Vous devez être manager ou administrateur pour supprimer un article', statusCode: 403)]
    public function delete(Request $request, Products $product): Response
    {
        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('products_index', [], Response::HTTP_SEE_OTHER);
    }

    public function printInvoice()
    {
        $invoice = new InvoicePrinter();

        // $invoice->setLogo("images/sample1.jpg");   //logo image path
        $invoice->setColor("#007fff");      // pdf color scheme
        $invoice->setType("Sale Invoice");    // Invoice Type
        $invoice->setReference("INV-55033645");   // Reference
        $invoice->setDate(date('M dS ,Y',time()));   //Billing Date
        $invoice->setTime(date('h:i:s A',time()));   //Billing Time
        $invoice->setDue(date('M dS ,Y',strtotime('+3 months')));    // Due Date
        $invoice->setFrom(array("Seller Name","Sample Company Name","128 AA Juanita Ave","Glendora , CA 91740"));
        $invoice->setTo(array("Purchaser Name","Sample Company Name","128 AA Juanita Ave","Glendora , CA 91740"));

        $invoice->addItem("AMD Athlon X2DC-7450","2.4GHz/1GB/160GB/SMP-DVD/VB",6,0,580,0,3480);
        $invoice->addItem("PDC-E5300","2.6GHz/1GB/320GB/SMP-DVD/FDD/VB",4,0,645,0,2580);
        $invoice->addItem('LG 18.5" WLCD',"",10,0,230,0,2300);
        $invoice->addItem("HP LaserJet 5200","",1,0,1100,0,1100);

        $invoice->addTotal("Total",9460);
        $invoice->addTotal("VAT 21%",1986.6);
        $invoice->addTotal("Total due",11446.6,true);

        $invoice->addBadge("Payment Paid");

        $invoice->addTitle("Important Notice");

        $invoice->addParagraph("No item will be replaced or refunded if you don't have the invoice with you.");

        $invoice->setFooternote("My Company Name Here");

        $invoice->render('example1.pdf','D');
    }


}
