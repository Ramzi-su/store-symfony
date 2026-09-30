<?php

namespace App\Command;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:load-products', description: 'Insère les produits de démonstration dans la base de données')]
class LoadProductsCommand extends Command
{
    private const PRODUCTS = [
        ['name' => 'iPhone 15 Pro',        'category' => 'Téléphones',   'price' => 129900, 'stock' => 10, 'isSale' => false, 'description' => 'Le dernier iPhone avec puce A17 Pro, titane, et capteur photo 48 MP.',        'image' => 'uploads/products/iphone-15-pro.jpg'],
        ['name' => 'Samsung Galaxy S24',   'category' => 'Téléphones',   'price' => 99900,  'stock' => 15, 'isSale' => false, 'description' => 'Galaxy AI intégré, écran Dynamic AMOLED 2X, zoom 50x.',                         'image' => 'uploads/products/samsung-galaxy-s24.jpg'],
        ['name' => 'MacBook Pro M3',       'category' => 'Ordinateurs',  'price' => 199900, 'stock' => 5,  'isSale' => false, 'description' => 'Puce Apple M3, 18h d\'autonomie, écran Liquid Retina XDR.',                     'image' => 'uploads/products/macbook-pro-m3.jpg'],
        ['name' => 'Dell XPS 15',          'category' => 'Ordinateurs',  'price' => 149900, 'stock' => 8,  'isSale' => false, 'description' => 'Intel Core i9, écran OLED 4K tactile, design ultra-fin.',                      'image' => 'uploads/products/dell-xps-15.jpg'],
        ['name' => 'AirPods Pro',          'category' => 'Accessoires',  'price' => 27900,  'stock' => 20, 'isSale' => true,  'description' => 'Réduction active du bruit H2, audio spatial personnalisé.',                     'image' => 'uploads/products/airpods-pro.jpg'],
        ['name' => 'Coque iPhone 15',      'category' => 'Accessoires',  'price' => 2900,   'stock' => 50, 'isSale' => false, 'description' => 'Coque premium en silicone, compatible MagSafe, toutes couleurs.',              'image' => 'uploads/products/coque-iphone-15.jpg'],
        ['name' => 'iPad Air',             'category' => 'Tablettes',    'price' => 79900,  'stock' => 12, 'isSale' => false, 'description' => 'Puce M2, écran Liquid Retina 10.9 pouces, compatible Apple Pencil.',           'image' => 'uploads/products/ipad-air.jpg'],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private ProductRepository $repo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Chargement / mise à jour des produits');

        $inserted = 0;
        $updated  = 0;

        foreach (self::PRODUCTS as $data) {
            // Génère le slug pour retrouver le produit existant
            $tmp = new Product();
            $tmp->setName($data['name']);
            $tmp->generateSlug();
            $slug = $tmp->getSlug();

            $product = $this->repo->findOneBy(['slug' => $slug]);

            if ($product) {
                // Met à jour l'image du produit existant
                $product->setImage($data['image']);
                $io->text("↻ {$data['name']} — image mise à jour");
                $updated++;
            } else {
                // Crée un nouveau produit
                $product = new Product();
                $product->setName($data['name']);
                $product->setCategory($data['category']);
                $product->setPrice($data['price']);
                $product->setStock($data['stock']);
                $product->setIsSale($data['isSale']);
                $product->setDescription($data['description']);
                $product->setCreatedAt(new \DateTimeImmutable());
                $product->setImage($data['image']);
                $product->generateSlug();
                $this->em->persist($product);
                $io->text("✔ {$data['name']} ({$data['category']}) — créé");
                $inserted++;
            }
        }

        $this->em->flush();

        $io->success("$inserted produit(s) créé(s), $updated mis à jour avec image !");
        return Command::SUCCESS;
    }
}
